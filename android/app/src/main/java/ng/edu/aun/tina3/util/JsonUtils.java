package ng.edu.aun.tina3.util;


import org.json.JSONException;
import org.json.JSONObject;

import java.util.HashMap;
import java.util.Iterator;
import java.util.Map;

import ng.edu.aun.tina3.rest.model.User;

public class JsonUtils extends com.litigy.lib.java.util.JsonUtils {

    static{
        registerSerializer(User.class, new User.UserSerializer(), new User.UserDerserialzier());
    }

    public static <T> T fromJson(String jsonString, Class<T> tClass){
        return getBuilder().create().fromJson(jsonString, tClass);
    }

    public static <T> String toJson(T t){
        return getBuilder().create().toJson(t);
    }

    public static Map<String, String> toMap(String jsonString) throws JSONException {
        Map<String, String> map = new HashMap<String, String>();
        JSONObject jObject = new JSONObject(jsonString);
        Iterator<?> keys = jObject.keys();

        while( keys.hasNext() ){
            String key = (String)keys.next();
            String value = jObject.getString(key);
            map.put(key, value);

        }
        return map;
    }

    public static class Data extends HashMap<String, String>{

    }


}
