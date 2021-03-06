package ng.edu.aun.tina3.auth;

import android.accounts.AbstractAccountAuthenticator;
import android.accounts.Account;
import android.accounts.AccountAuthenticatorResponse;
import android.accounts.AccountManager;
import android.accounts.AccountManagerCallback;
import android.accounts.AccountManagerFuture;
import android.accounts.NetworkErrorException;
import android.content.Context;
import android.content.Intent;
import android.os.AsyncTask;
import android.os.Build;
import android.os.Bundle;
import android.os.IBinder;
import android.support.annotation.Nullable;

import com.litigy.lib.java.error.LitigyException;
import com.litigy.lib.java.generic.DoubleReceiver;
import com.litigy.lib.java.generic.Receiver;
import com.litigy.lib.java.security.Crypto;
import com.litigy.lib.java.util.Time;
import com.litigy.lib.java.util.Value;

import java.io.IOException;
import java.util.Map;
import java.util.concurrent.ConcurrentLinkedQueue;

import ng.edu.aun.tina3.Application;
import ng.edu.aun.tina3.R;
import ng.edu.aun.tina3.data.Database;
import ng.edu.aun.tina3.data.Preferences;
import ng.edu.aun.tina3.rest.api.AuthApi;
import ng.edu.aun.tina3.rest.model.Token;
import ng.edu.aun.tina3.rest.model.User;
import ng.edu.aun.tina3.error.ConflictException;
import ng.edu.aun.tina3.error.NotFoundException;
import ng.edu.aun.tina3.service.ActionService;
import ng.edu.aun.tina3.service.PredictionService;
import ng.edu.aun.tina3.util.JsonUtils;
import ng.edu.aun.tina3.util.Log;

public class Authenticator extends AbstractAccountAuthenticator {

    private static Authenticator INSTANCE = null;
    public static class Constants {
        private static final String DATA_ENCRYPTION_KEY = "mi33nj846VzCERy4CFqMs1PGcftm7eCB";
        private static final String TOKEN_ENCRYPTION_KEY = "en80VyNoI8bz4kSR8sEXs5c9TL8Op7ke1";

        public static final String USER_UPDATE_INTENT = "ng.edu.aun.tina3.auth.Authenticator.user.UPDATE";
    }

    public static Authenticator getInstance(){
        return Value.IS.nullValue(INSTANCE) ? new Authenticator(Application.getInstance()) : INSTANCE;
    }

    private static volatile User user = null;

    {
        INSTANCE = this;
    }

    private static ConcurrentLinkedQueue<DoubleReceiver<String, LitigyException>> tokenListeners = new ConcurrentLinkedQueue<>();


    public Authenticator(Context context) {
        super(context);
    }

    @Override
    public Bundle editProperties(AccountAuthenticatorResponse response, String accountType) {
        return null;
    }

    @Override
    public Bundle addAccount(AccountAuthenticatorResponse response, String accountType, String authTokenType, String[] requiredFeatures, Bundle options) throws NetworkErrorException {
        return null;
    }

    @Override
    public Bundle confirmCredentials(AccountAuthenticatorResponse response, Account account, Bundle options) throws NetworkErrorException {
        return null;
    }

    @Override
    public Bundle getAuthToken(AccountAuthenticatorResponse response, Account account, String authTokenType, Bundle options) throws NetworkErrorException {
        return null;
    }

    @Override
    public String getAuthTokenLabel(String authTokenType) {
        return null;
    }

    @Override
    public Bundle updateCredentials(AccountAuthenticatorResponse response, Account account, String authTokenType, Bundle options) throws NetworkErrorException {
        return null;
    }

    @Override
    public Bundle hasFeatures(AccountAuthenticatorResponse response, Account account, String[] features) throws NetworkErrorException {
        return null;
    }


    public User getUser(boolean reload) throws Exception {
        if(!reload && !Value.IS.nullValue(user))
            return user;
        AccountManager accountManager = AccountManager.get(Application.getInstance());
        Account[] accounts = accountManager.getAccountsByType(Application.getInstance().getString(R.string.account_type));
        Log.d("Accounts gotten, size is "+accounts.length);
        if(Value.IS.emptyValue(accounts))
            throw new NotFoundException("no accounts found");
        Account account = accounts[0];
        Log.d("Account: "+account.toString());
        String data = accountManager.getUserData(account, "json");
        Log.d("Data is "+data);
        String decrypted = Crypto.AES.decrypt(Constants.DATA_ENCRYPTION_KEY, data);
        Log.d("Decrypted data is "+decrypted);
        User user = JsonUtils.fromJson(decrypted, User.class);
        if(Value.IS.nullValue(user))
            throw new NotFoundException("error parsing json to user");
        return (Authenticator.user = user);
    }

    public AsyncTask getUserAsync(final boolean reload, final DoubleReceiver<User, Exception> receiver){
        Log.d("Getting user async");
        return new AsyncTask<Void, Void, Object>(){
            @Override
            protected Object doInBackground(Void... params) {
                try {
                    return getUser(reload);
                } catch (Exception e) {
                    Log.d("Exception getting user, is.");
                    return e;
                }
            }

            @Override
            protected void onPostExecute(Object o) {
                if(o instanceof User){
                    receiver.onReceive1((User) o);
                }else{
                    receiver.onReceive2((Exception)o);
                }
            }
        }.execute();
    }

    public User setUser(User user) throws Exception {
        Log.d("Setting user");
        AccountManager accountManager = AccountManager.get(Application.getInstance());
        Account[] accounts = accountManager.getAccountsByType(Application.getInstance().getString(R.string.account_type));
        if(!Value.IS.emptyValue(accounts))
            throw new ConflictException("accounts found, only one allowed");
        String access_token = null, refresh_token = null;
        Long expiresAt = null;
        try{
            access_token = user.getData().get("access_token");
            refresh_token = user.getData().get("refresh_token");
            expiresAt = Value.TO.longValue(user.getData().get("expiresAt"));
        }catch (Exception ignored){

        }
        user.setPassword(null).setData(null);
        Bundle bundle = new Bundle();
        bundle.putString("json", Crypto.AES.encrypt(Constants.DATA_ENCRYPTION_KEY, JsonUtils.toJson(user)));
        Account account = toAccount(user);
        if(accountManager.addAccountExplicitly(account, null, bundle)){
            saveTokens(account, accountManager, access_token, refresh_token, expiresAt);
            return user;
        }
        throw new LitigyException("error");
    }

    public AsyncTask setUserAsync(User user, final DoubleReceiver<User, LitigyException> callbackReceiver){
        Log.d("Setting user async");
        return new AsyncTask<User, Void, Object>(){
            @Override
            protected Object doInBackground(User... params) {
                try {
                    return setUser(params[0]);
                } catch (Exception e) {
                    return new LitigyException(e.getMessage());
                }
            }

            @Override
            protected void onPostExecute(Object o) {
                Log.d("Returning from set user asnync");
                if (!Value.IS.nullValue(callbackReceiver)) {
                    if(o instanceof User){
                        callbackReceiver.onReceive1((User)o);
                    }else{
                        callbackReceiver.onReceive2((LitigyException)o);
                    }
                }
            }
        }.execute(user);
    }

    public User updateUser(User user) throws Exception{
        Log.d("updating user");
        AccountManager accountManager = AccountManager.get(Application.getInstance());
        Account[] accounts = accountManager.getAccountsByType(Application.getInstance().getString(R.string.account_type));
        if(Value.IS.emptyValue(accounts))
            throw new NotFoundException("no accounts");
        user.setPassword(null).setData(null);
        Account account = toAccount(user);
        accountManager.setUserData(account, "json", Crypto.AES.encrypt(Constants.DATA_ENCRYPTION_KEY, JsonUtils.toJson(user)));
        Authenticator.user = user;
        Application.getInstance().sendBroadcast(new Intent(Constants.USER_UPDATE_INTENT));
        Log.d("User updated");
        return Authenticator.user;
    }

    public AsyncTask updateUserAsync(User user, final DoubleReceiver<User, LitigyException> callbackReceiver){
        return new AsyncTask<User, Void, Object>(){
            @Override
            protected Object doInBackground(User... params) {
                try {
                    return updateUser(params[0]);
                } catch (Exception e) {
                    Log.d("Exception updating user: "+e.getMessage());
                    return new LitigyException(e.getMessage());
                }
            }

            @Override
            protected void onPostExecute(Object o) {
                if (!Value.IS.nullValue(callbackReceiver)) {
                    if(o instanceof User){
                        callbackReceiver.onReceive1((User)o);
                    }else{
                        callbackReceiver.onReceive2((LitigyException)o);
                    }
                }
            }
        }.execute(user);
    }

    public Account toAccount(User user){
        return new Account(user.getUsername(), Application.getInstance().getString(R.string.account_type));
    }

    public static class Service extends android.app.Service {
        @Nullable
        @Override
        public IBinder onBind(Intent intent) {
            return getInstance().getIBinder();
        }
    }

    public void getAccessToken(DoubleReceiver<String, LitigyException> tokenListener) throws NotFoundException {
        Log.d("Getting access token");
        tokenListeners.add(tokenListener);
        if(tokenListeners.size()==1){
            requestToken();
        }
    }

    private void requestToken() throws NotFoundException {
        Log.d("New token request dispatched.");
        try {
            AccountManager manager = AccountManager.get(Application.getInstance());
            Account account = manager.getAccountsByType(Application.getInstance().getString(R.string.account_type))[0];
            Long expiresAt = Value.TO.longValue(manager.getUserData(account, "expiresAt"));
            Log.d("Token expires at "+expiresAt);
            if (Time.nowDateTime().isBefore(expiresAt)) {
                Log.d("Token still valid, dispatching");
                dispatchToken(Crypto.AES.decrypt(Constants.TOKEN_ENCRYPTION_KEY,
                        manager.peekAuthToken(account, "access_token")));
                return;
            }
            Log.d("Token expired, refreshing");
            refreshToken(account, manager, Crypto.AES.decrypt(Constants.TOKEN_ENCRYPTION_KEY,
                    manager.peekAuthToken(account, "refresh_token")));
        }catch (Exception ignored2){
            Log.d("Exception is "+ignored2.getMessage());
            throw new NotFoundException("error obtaining token");
        }
    }

    private void dispatchToken(String token){
        Log.d("Dispatching token: "+token);
        while (!tokenListeners.isEmpty())
            tokenListeners.poll().onReceive1(token);
    }

    private void dispatchError(LitigyException exception){
        Log.d("Dispatching error: "+exception.getMessage());
        while (!tokenListeners.isEmpty())
            tokenListeners.poll().onReceive2(exception);
    }

    private void refreshToken(final Account account, final AccountManager accountManager, final String refreshToken) {
        Log.d("Refreshing token with token: "+refreshToken);
        AuthApi.refreshTokens(refreshToken, new DoubleReceiver<User, LitigyException>() {
            @Override
            public void onReceive(User user, LitigyException e) {

            }

            @Override
            public void onReceive1(User user) {
                String access_token = null, refresh_token = null;
                Long expiresAt = null;
                try{
                    access_token = user.getData().get("access_token");
                    refresh_token = user.getData().get("refresh_token");
                    expiresAt = Value.TO.longValue(user.getData().get("expiresAt"));
                }catch (Exception ignored){

                }
                try {
                    saveTokens(account, accountManager, access_token, refresh_token, expiresAt);
                    dispatchToken(access_token);
                } catch (Exception e) {
                    dispatchError(new LitigyException(e.getMessage()));
                }
            }

            @Override
            public void onReceive2(LitigyException e) {
                Log.d("Litigy exception refreshing tokens, is "+e.getMessage());
                dispatchError(e);
            }
        });
    }

    public void logout(){
        AccountManager accountManager = AccountManager.get(Application.getInstance());
        Account[] accounts = accountManager.getAccountsByType(Application.getInstance().getString(R.string.account_type));
        if(!Value.IS.emptyValue(accounts)){
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.LOLLIPOP_MR1) {
                accountManager.removeAccountExplicitly(accounts[0]);
            }else if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.LOLLIPOP_MR1) {
                    accountManager.removeAccount(accounts[0], null, new AccountManagerCallback<Bundle>() {
                        @Override
                        public void run(AccountManagerFuture<Bundle> future) {

                        }
                    }, null);
            }else{
                accountManager.removeAccount(null, new AccountManagerCallback<Boolean>() {
                    @Override
                    public void run(AccountManagerFuture<Boolean> future) {

                    }
                }, null);
            }
        }
        Application.getInstance().stopService(new Intent(Application.getInstance(), PredictionService.class));
        Application.getInstance().stopService(new Intent(Application.getInstance(), ActionService.class));
        ActionService.cancelAlarm(Application.getInstance());
        PredictionService.AlarmScheduler.cancelAlarm(Application.getInstance());
        Database.getInstance().reset();
        Preferences.getInstance().reset();
        user = null;
    }

    public AsyncTask logoutAsync(final Receiver<Void> callbackReceiver){
        return new AsyncTask<Void, Void, Void>(){
            @Override
            protected Void doInBackground(Void... params) {
                logout();
                return null;
            }

            @Override
            protected void onPostExecute(Void aVoid) {
                callbackReceiver.onReceive(null);
            }
        }.execute();
    }

    private void saveTokens(Account account, AccountManager accountManager, String accessToken, String refreshToken, Long expiresAt) throws Exception {
        Log.d("Saving tokens");
        if(Value.IS.ANY.nullValue(accessToken, refreshToken, expiresAt))
            return;
        accountManager.setUserData(account, "expiresAt", Value.TO.stringValue(expiresAt));
        accountManager.setAuthToken(account, "access_token", Crypto.AES.encrypt(Constants.TOKEN_ENCRYPTION_KEY, accessToken));
        accountManager.setAuthToken(account, "refresh_token", Crypto.AES.encrypt(Constants.TOKEN_ENCRYPTION_KEY,  refreshToken));
    }

}
