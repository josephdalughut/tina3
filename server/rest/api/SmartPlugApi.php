<?php

/**
 * Created by PhpStorm.
 * User: joeyblack
 * Date: 10/13/16
 * Time: 5:23 PM
 */

require_once 'Api.php';
require_once 'AbstractApi.php';
require_once '../model/User.php';
require_once '../model/Token.php';
require_once '../model/SmartPlug.php';
require_once '../util/HTTPStatusCode.php';
require_once '../util/Time.php';

class SmartPlugApi extends AbstractApi
{

    /**
     * @param array $args
     * @return string
     */
    public function create($args){
        if(!$this->method == "POST"){
            return $this->_response("only POST requests supported", HTTPStatusCode::$METHOD_NOT_ALLOWED);
        }
        if(!self::checkParams($args, "id")){
            return $this->_response("required parameter not found", HTTPStatusCode::$BAD_REQUEST);
        }
        $smartPlugId = $args["id"];
        $user = Token::getUserFromRequest($this, $this->_getDatabase());
        if(is_string($user)){
            return $user;
        }
        $findSQL = "select * from ".SmartPlug::$database_tableName." where ".SmartPlug::$database_tableColumn_id." ='".$smartPlugId."'";
        /** @var mysqli_result $res */
        $res = $this->_getDatabase()->query($findSQL);
        if(!$res){
            return $this->_response("Internal error", HTTPStatusCode::$SERVICE_UNAVAILABLE);
        }
        /** @var SmartPlug */
        $smartPlug = null;
        if ($res->num_rows > 0){
            $smartPlug = SmartPlug::fromSQL(mysqli_fetch_row($res));
            if($smartPlug->getUserId()!=$user->getId()) {
                return $this->_response("Conflict: smartPlug", HTTPStatusCode::$CONFLICT);
            }
        }

        $command = $smartPlugId."_SAY";

        $script = "python ../../rf/send.py \"".$command."\"";
        $result = shell_exec($script);
        $result = str_replace("\n", "", str_replace("\"", "", $result));
        $VAL = "OFF";
        if (preg_match('/^ERROR/', $result)) {
            return $this->_response("Not found, return successful from script with error: ".$result, HTTPStatusCode::$NOT_FOUND);
        } else{
            $arr = $this->_responseToArr($result);
            $UUID = $arr["UUID"];
            //$CMD = $arr["CMD"];
            $VAL = $arr["VAL"];
            if($UUID != $smartPlugId){
                return $this->_response("Not found, UUID mismatch", HTTPStatusCode::$NOT_FOUND);
            }
        }
        $smartPlug = new SmartPlug();
        $now = Time::now();
        $smartPlug->setUserId($user->getId())->setState($VAL)->setId($smartPlugId)->setCreatedAt($now)->setUpdatedAt($now);
        $replaceSQL = SmartPlug::wrapToReplaceSQL($smartPlug);
        $res = $this->_getDatabase()->query($replaceSQL);
        if(!$res){
            return $this->_response("Failed", HTTPStatusCode::$SERVICE_UNAVAILABLE);
        }
        return $this->_response($smartPlug, HTTPStatusCode::$OK);
    }

    public function testPy($args){
        if(!self::checkParams($args, "command")){
            return $this->_response("required parameter not found", HTTPStatusCode::$BAD_REQUEST);
        }
        $command = "python ".$args["command"];
        echo "Command is ".$command;
        $result = shell_exec($command);
        echo "Response is ".$result;
        return $this->_response($result, HTTPStatusCode::$OK);
    }

    public function testSerSP($args){
        $findSQL = "select * from ".SmartPlug::$database_tableName;
        /** @var mysqli_result $res */
        $res = $this->_getDatabase()->query($findSQL);
        if(!$res){
            return $this->_response("Internal error", HTTPStatusCode::$SERVICE_UNAVAILABLE);
        }
        /** @var SmartPlug */
        $smartPlug = null;
        if ($res->num_rows > 0){
            $smartPlug = SmartPlug::fromSQL(mysqli_fetch_row($res));
        }
        return $this->_response($smartPlug, HTTPStatusCode::$OK);
    }

    /**
     * @param array $args
     * @return string
     */
    public function rename($args){
        if(!$this->method == "POST"){
            return $this->_response("only POST requests supported", HTTPStatusCode::$METHOD_NOT_ALLOWED);
        }
        if(!self::checkParams($args, "id", "name")){
            return $this->_response("required parameter not found", HTTPStatusCode::$BAD_REQUEST);
        }
        $smartPlugId = $args["id"];
        $name = $args["name"];
        $user = Token::getUserFromRequest($this, $this->_getDatabase());
        if(is_string($user)){
            return $user;
        }
        $findSQL = "select * from ".SmartPlug::$database_tableName." where ".SmartPlug::$database_tableColumn_id." ='".$smartPlugId."'";
        /** @var mysqli_result $res */
        $res = $this->_getDatabase()->query($findSQL);
        if(!$res){
            return $this->_response("Internal error", HTTPStatusCode::$SERVICE_UNAVAILABLE);
        }
        if ($res->num_rows < 1){
            return $this->_response("Not Found: smartPlug", HTTPStatusCode::$NOT_FOUND);
        }
        $smartPlug = SmartPlug::fromSQL(mysqli_fetch_row($res));
        $now = Time::now();
        $smartPlug->setName($name)->setUpdatedAt($now);
        $updateSQL = SmartPlug::wrapToUpdateSQL($smartPlug);
        if(!$this->_getDatabase()->query($updateSQL)){
            return $this->_response("Failed", HTTPStatusCode::$SERVICE_UNAVAILABLE);
        }
        return $this->_response($smartPlug, HTTPStatusCode::$OK);
    }


    /**
     * @param array $args
     * @return string
     */
    public function delete($args){
        if(!$this->method == "DELETE"){
            return $this->_response("only DELETE requests supported", HTTPStatusCode::$METHOD_NOT_ALLOWED);
        }
        if(!self::checkParams($args, "id")){
            return $this->_response("required parameter not found", HTTPStatusCode::$BAD_REQUEST);
        }
        $smartPlugId = $args["id"];
        $user = Token::getUserFromRequest($this, $this->_getDatabase());
        if(!$user instanceof User){
            return $user;
        }
        $deleteSQL = "delete from ".SmartPlug::$database_tableName." where ".Entity::$database_tableColumn_id
            ."='".$smartPlugId."' and ".SmartPlug::$database_tableColumn_userId." ='".$user->getId()."'";
        $res = $this->_getDatabase()->query($deleteSQL);
        if(!$res){
            return $this->_response("Not found", HTTPStatusCode::$NOT_FOUND);
        }
        $smartPlug = new SmartPlug();
        $smartPlug->setId($smartPlugId);
        return $this->_response($smartPlug, HTTPStatusCode::$OK);
    }

    /**
     * @param array $args
     * @return string
     */
    public function switchOn($args){
        if(!$this->method == "PUT"){
            return $this->_response("only PUT requests supported", HTTPStatusCode::$METHOD_NOT_ALLOWED);
        }
        if(!self::checkParams($args, "id")){
            return $this->_response("required parameter not found", HTTPStatusCode::$BAD_REQUEST);
        }
        $smartPlugId = $args["id"];
        $user = Token::getUserFromRequest($this, $this->_getDatabase());
        if(is_string($user)){
            return $user;
        }
        $findSQL = "select * from ".SmartPlug::$database_tableName." where ".SmartPlug::$database_tableColumn_id." ='".$smartPlugId."'";
        /** @var mysqli_result $res */
        $res = $this->_getDatabase()->query($findSQL);
        if(!$res){
            return $this->_response("Internal error", HTTPStatusCode::$SERVICE_UNAVAILABLE);
        }
        if($res->num_rows<1){
            return $this->_response("Not found, query empty", HTTPStatusCode::$NOT_FOUND);
        }
        $smartPlug = SmartPlug::fromSQL(mysqli_fetch_row($res));
        if($smartPlug->getUserId() != $user->getId()){
            return $this->_response("Forbidden", HTTPStatusCode::$FORBIDDEN);
        }

        $command = $smartPlug->getId()."_TO_ON";
        $script = "python ../../rf/send.py \"".$command."\"";
        $result = shell_exec($script);
        $result = str_replace("\n", "", str_replace("\"", "", $result));
        if(preg_match('/^ERROR/', $result)){
            return $this->_response("Not found, no reply", HTTPStatusCode::$NOT_FOUND);
        }else{
            $arr = $this->_responseToArr($result);
            $UUID = $arr["UUID"];
            $CMD = $arr["CMD"];
            $VAL = $arr["VAL"];
            if($UUID != $smartPlugId){
                return $this->_response("Not found, UUID mismatch: ".$result, HTTPStatusCode::$NOT_FOUND);
            }
            switch ($VAL){
                case "ON":
                case "OFF":
                    $smartPlug->setState($VAL);
                    $update = $this->_getDatabase()->query(SmartPlug::wrapToUpdateSQL($smartPlug));
                    if(!$update){
                        return $this->_response("Internal error", HTTPStatusCode::$SERVICE_UNAVAILABLE);
                    }
                    break;
                default:
                    return $this->_response("Not found, value not recognized: ".$VAL, HTTPStatusCode::$NOT_FOUND);
            }
            return $this->_response($smartPlug, HTTPStatusCode::$OK);
        }
    }

    /**
     * @param array $args
     * @return string
     */
    public function switchOff($args){
        if(!$this->method == "PUT"){
            return $this->_response("only PUT requests supported", HTTPStatusCode::$METHOD_NOT_ALLOWED);
        }
        if(!self::checkParams($args, "id")){
            return $this->_response("required parameter not found", HTTPStatusCode::$BAD_REQUEST);
        }
        $smartPlugId = $args["id"];
        $user = Token::getUserFromRequest($this, $this->_getDatabase());
        if(is_string($user)){
            return $user;
        }
        $findSQL = "select * from ".SmartPlug::$database_tableName." where ".SmartPlug::$database_tableColumn_id." ='".$smartPlugId."'";
        /** @var mysqli_result $res */
        $res = $this->_getDatabase()->query($findSQL);
        if(!$res){
            return $this->_response("Internal error", HTTPStatusCode::$SERVICE_UNAVAILABLE);
        }
        if($res->num_rows<1){
            return $this->_response("Not found, query empty", HTTPStatusCode::$NOT_FOUND);
        }
        $smartPlug = SmartPlug::fromSQL(mysqli_fetch_row($res));
        if($smartPlug->getUserId() != $user->getId()){
            return $this->_response("Forbidden", HTTPStatusCode::$FORBIDDEN);
        }

        $command = $smartPlug->getId()."_TO_OFF";
        $script = "python ../../rf/send.py \"".$command."\"";
        $result = shell_exec($script);
        $result = str_replace("\n", "", str_replace("\"", "", $result));
        if(preg_match('/^ERROR/', $result)){
            return $this->_response("Not found, no reply", HTTPStatusCode::$NOT_FOUND);
        }else{
            $arr = $this->_responseToArr($result);
            $UUID = $arr["UUID"];
            $CMD = $arr["CMD"];
            $VAL = $arr["VAL"];
            if($UUID != $smartPlugId){
                return $this->_response("Not found, UUID mismatch: ".$result, HTTPStatusCode::$NOT_FOUND);
            }
            switch ($VAL){
                case "ON":
                case "OFF":
                    $smartPlug->setState($VAL);
                    $update = $this->_getDatabase()->query(SmartPlug::wrapToUpdateSQL($smartPlug));
                    if(!$update){
                        return $this->_response("Internal error", HTTPStatusCode::$SERVICE_UNAVAILABLE);
                    }
                    break;
                default:
                    return $this->_response("Not found, value not recognized: ".$VAL, HTTPStatusCode::$NOT_FOUND);
            }
            return $this->_response($smartPlug, HTTPStatusCode::$OK);
        }
    }

    /**
     * @param array $args
     * @return string
     */
    public function get($args){
        if(!$this->method == "GET"){
            return $this->_response("Only GET requests supported", HTTPStatusCode::$METHOD_NOT_ALLOWED);
        }
        if(!self::checkParams($args, "id")){
            return $this->_response("required parameter not found", HTTPStatusCode::$BAD_REQUEST);
        }
        $smartPlugId = $args["id"];
        $user = Token::getUserFromRequest($this, $this->_getDatabase());
        if(!$user instanceof User){
            return $user;
        }
        $findSQL = "select * from ".SmartPlug::$database_tableName." where ".SmartPlug::$database_tableColumn_id." ='".$smartPlugId->getId()."'";
        /** @var mysqli_result $res */
        $res = $this->_getDatabase()->query($findSQL);
        if(!$res){
            return $this->_response("Internal error", HTTPStatusCode::$SERVICE_UNAVAILABLE);
        }
        if($res->num_rows<1){
            return $this->_response("Not found", HTTPStatusCode::$NOT_FOUND);
        }
        $smartPlug = SmartPlug::fromSQL(mysqli_fetch_row($res));
        if($smartPlug->getUserId() != $user->getId()){
            return $this->_response("Forbidden", HTTPStatusCode::$FORBIDDEN);
        }
        return $this->_response($smartPlug, HTTPStatusCode::$OK);
    }

    /**
     * @param array $args
     * @return string
     */
    public function gets($args){
        if(!$this->method == "GET"){
            return $this->_response("Only GET requests supported", HTTPStatusCode::$METHOD_NOT_ALLOWED);
        }
        /*if(!self::checkParams($args, "id")){
            return $this->_response("required parameter not found", HTTPStatusCode::$BAD_REQUEST);
        }*/
        $user = Token::getUserFromRequest($this, $this->_getDatabase());
        if(!$user instanceof User){
            return $user;
        }
        $findSQL = "select * from ".SmartPlug::$database_tableName." where ".SmartPlug::$database_tableColumn_userId." =".$user->getId();
        /** @var mysqli_result $res */
        $res = $this->_getDatabase()->query($findSQL);
        if(!$res){
            return $this->_response("Internal error, query error", HTTPStatusCode::$SERVICE_UNAVAILABLE);
        }
        /*
        if($res->num_rows<1){
            return $this->_response("Not found", HTTPStatusCode::$NOT_FOUND);
        }
        */
        $smartPlugs = array();
        while ($row = mysqli_fetch_array($res, MYSQLI_NUM))
        {
            $smartPlug = SmartPlug::fromSQL($row);
            array_push($smartPlugs, $smartPlug);
        }
        return $this->_response($smartPlugs, HTTPStatusCode::$OK);
    }


    /**
     * @param array $args
     * @return string
     */
    public function say($args){
        if(!$this->method == "GET"){
            return $this->_response("Only GET requests supported", HTTPStatusCode::$METHOD_NOT_ALLOWED);
        }
        if(!self::checkParams($args, "id")){
            return $this->_response("required parameter not found", HTTPStatusCode::$BAD_REQUEST);
        }
        $smartPlugId = $args["id"];
        $user = Token::getUserFromRequest($this, $this->_getDatabase());
        if(!$user instanceof User){
            return $user;
        }
        $findSQL = "select * from ".SmartPlug::$database_tableName." where ".SmartPlug::$database_tableColumn_id." ='".$smartPlugId->getId()."'";
        /** @var mysqli_result $res */
        $res = $this->_getDatabase()->query($findSQL);
        if(!$res){
            return $this->_response("Internal error", HTTPStatusCode::$SERVICE_UNAVAILABLE);
        }
        if($res->num_rows<1){
            return $this->_response("Not found", HTTPStatusCode::$NOT_FOUND);
        }
        $smartPlug = SmartPlug::fromSQL(mysqli_fetch_row($res));
        if($smartPlug->getUserId() != $user->getId()){
            return $this->_response("Forbidden", HTTPStatusCode::$FORBIDDEN);
        }
        $command = $smartPlug->getId()."_SAY";
        $script = "python ../../rf/send.py \"".$command."\"";
        $result = shell_exec($script);
        $result = str_replace("\n", "", str_replace("\"", "", $result));
        if(preg_match('/^ERROR/', $result)){
            return $this->_response("Not found", HTTPStatusCode::$NOT_FOUND);
        }else{
            $arr = $this->_responseToArr($result);
            $UUID = $arr["UUID"];
            $CMD = $arr["CMD"];
            $VAL = $arr["VAL"];
            if($UUID != $smartPlugId){
                return $this->_response("Not found", HTTPStatusCode::$NOT_FOUND);
            }
            switch ($VAL){
                case "ON":
                case "OFF":
                    $smartPlug->setState($VAL);
                    $update = $this->_getDatabase()->query(SmartPlug::wrapToUpdateSQL($smartPlug));
                    if(!$update){
                        return $this->_response("Internal error", HTTPStatusCode::$SERVICE_UNAVAILABLE);
                    }
                    break;
                default:
                    return $this->_response("Not found", HTTPStatusCode::$NOT_FOUND);
            }
            return $this->_response($smartPlug, HTTPStatusCode::$OK);
        }
    }

    public function split($arr){
        $value= $arr["value"];
        $response = $this->_responseToArr($value);
        return $this->_response($response, HTTPStatusCode::$OK);
    }

    /**
     * @param string $value
     * @return array
     */
    public function _responseToArr($value){
        $uuid_idx = strpos($value, "_");
        $cmd_idx = strpos($value, "_", $uuid_idx + 1);
        $val_idx = strpos($value, "_", $cmd_idx + 1);
        $uuid = substr($value, 0, $uuid_idx);
        $cmd = substr($value, $uuid_idx + 1, $cmd_idx - $uuid_idx-1);
        $val = substr($value, $cmd_idx + 1);
        return array (
          "UUID" => $uuid, "CMD" => $cmd, "VAL" => $val, "UUID_IDX" => $uuid_idx,
            "CMD_IDX" => $cmd_idx, "VAL_IDX" => $val_idx
        );
    }
}
