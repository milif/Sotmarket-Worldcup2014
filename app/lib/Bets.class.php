<?php 

require_once __DIR__.'/DB.class.php';
require_once __DIR__.'/User.class.php';

class Bets {
    static public function get(){
        $bets = array();
        $userBets = self::getUserBets();
        $rs = DB::query("SELECT * FROM bets ORDER BY `time`, `descr`");
        foreach($rs as $row){
            $userBet = isset($userBets[$row['id']]) ? $userBets[$row['id']] : NULL;
            $bets[] = array(
                'id' => $row['id'],
                'time' => strtotime($row['time']." Europe/Moscow") * 1000,
                'descr' => $row['descr'],
                'data' => json_decode($row['data'], true),
                'result' => json_decode($row['result'], true),
                'userResult' => $userBet && !is_null($userBet['result'])? (int)$userBet['result'] : NULL,
                'value' => $userBet ? json_decode($userBet['value'], true) : NULL,
                'score' => $userBet ? (int)$userBet['score'] : NULL
            );
        }
        return $bets;
    }
    static public function bet($betId, $value){
        $rs = DB::query("SELECT `time` FROM bets WHERE id = :betId", array(
            ':betId' => $betId
        ));
        if(!count($rs) || strtotime($rs[0]['time']." Europe/Moscow") < time()) return false;

        if($value[0] !== NULL && $value[1] !== NULL) {
            $rs = DB::query("SELECT id FROM user_bets WHERE bet_id = :betId AND user_key = :userKey", array(
                ':betId' => $betId,
                ':userKey' => User::getKey()
            ));
            $value[0] = (int)$value[0];
            $value[1] = (int)$value[1];
            if(count($rs)){
                DB::update("UPDATE user_bets SET value = :value WHERE id = ".$rs[0]['id'], array(
                    ':value' => json_encode($value)
                ));
            } else {
                DB::update("INSERT INTO user_bets (bet_id, user_key, value, user_id) VALUES (:betId, :userKey, :value, :userId)", array(
                    ':betId' => $betId,
                    ':userKey' => User::getKey(),
                    ':value' => json_encode($value),
                    ':userId' => User::isAuth() ? User::getKey() : NULL
                ));   
            }   
        } else {
            DB::update("DELETE FROM user_bets WHERE bet_id = :betId AND user_key = :userKey", array(
                ':betId' => $betId,
                ':userKey' => User::getKey()
            ));
        }
        return true;
    }
    static public function canBet(){
        if(!User::isAuth()){
            $rs = DB::query("SELECT COUNT(*) cc FROM user_bets WHERE user_key = :userKey", array(
                ':userKey' => User::getKey()
            ));
            //  Запрещаем анониму делать более 12 ставок
            if($rs[0]['cc'] >= 12) return false;
        }
        return true;
    }
    static public function getUserBets(){
        $rs = DB::query("SELECT `bet_id`, `value`, `result`, `score` FROM `user_bets` WHERE `user_key` = :userKey ;", array(
            ':userKey' => User::getKey()
        ));
        $userBets = array();
        foreach($rs as $row){
            $userBets[$row['bet_id']] = $row;
        }
        return $userBets;
    }
    static public function getUserResults($userKey = null){
        if(is_null($userKey)) $userKey = User::getKey();
        
        $key = 'betsUserResults'.$userKey;
        $userBets = Cache::get($key);
        if($userBets !== false) return $userBets;
        
        $rs = DB::query("SELECT `value`, a.`result` as betresult, `score`, `data`, b.`result`, b.`time`  FROM `user_bets` a LEFT JOIN `bets` b ON b.id = a.bet_id WHERE `user_key` = :userKey ORDER BY a.updated_at DESC;", array(
            ':userKey' => $userKey
        ));
        $userBets = array();
        foreach($rs as $row){
            $userBets[] = array(
                'data' => json_decode($row['data'], true),
                'time' => strtotime($row['time']." Europe/Moscow") * 1000,
                'score' => $row['score'],
                'value' => json_decode($row['value'], true),
                'result' => json_decode($row['result'], true),
                'userResult' => !is_null($row['betresult']) ? (int)$row['betresult'] : NULL
            );
        }
        
        Cache::set($key, $userBets, 300);
        return $userBets;
    }
}
