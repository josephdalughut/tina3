package ng.edu.aun.tina3.rest.api;

import com.google.gson.reflect.TypeToken;
import com.litigy.lib.java.error.LitigyException;
import com.litigy.lib.java.generic.DoubleReceiver;

import java.io.IOException;
import java.util.HashMap;
import java.util.Map;
import java.util.Objects;

import ng.edu.aun.tina3.rest.model.User;
import ng.edu.aun.tina3.rest.utils.TINA3Request;
import ng.edu.aun.tina3.util.Value;

/**
 * Created by joeyblack on 11/21/16.
 */

public class AuthApi extends Api {

    private static String PATH = "api/v1/auth/";

    public static void refreshTokens(String oldRefreshToken, DoubleReceiver<User, LitigyException> receiver){
        String method = "refresh";
        try {
            TINA3Request.<User>withAuthorization(null).withEndpoint(buildEndpoint(PATH, method)).withParam("refresh_token", oldRefreshToken)
                    .withCallbackReceiver(receiver).POST(User.class);
        } catch (IOException e) {
            receiver.onReceive2(LitigyException.consumeIOException(e));
        }
    }

    public static void login(String aunId, String password, DoubleReceiver<User, LitigyException> receiver){
        String method = "login";
        try {
            TINA3Request.<User>withAuthorization(null).withEndpoint(buildEndpoint(PATH, method))
                    .withParam("username", aunId)
                    .withParam("password", password)
                    .withCallbackReceiver(receiver).GET(User.class);
        } catch (IOException e) {
            receiver.onReceive2(LitigyException.consumeIOException(e));
        }
    }


}
