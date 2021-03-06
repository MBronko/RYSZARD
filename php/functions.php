<?php 
    class mail{
        function getoutbox($f3){
            global $db;
            $result = $db->exec('SELECT mail_date, mail_title, mail_receiver, mail_content, nickname FROM mail LEFT JOIN characters ON mail.mail_receiver=characters.char_id WHERE mail_sender=?', array($_SESSION["char_id"]));
            $f3->set('result', $result);
            echo \Template::instance()->render('mail/outbox.html');
        }
        function getinbox($f3){
            global $db;
            $result = $db->exec('SELECT mail_date, mail_title, mail_sender, mail_content, nickname FROM mail LEFT JOIN characters ON mail.mail_sender=characters.char_id WHERE mail_sender!=?', array($_SESSION["char_id"]));
            $f3->set('result', $result);
            echo \Template::instance()->render('mail/inbox.html');
        }
        function getmail($f3){
            echo \Template::instance()->render('mail/mail.html');    
        }
        function postmail($f3){
            global $db;
            if($result = $db->exec('SELECT char_id FROM characters WHERE nickname=? AND server_id=?', array($_POST["address"], $_SESSION["server"]))){
                $db->exec('INSERT INTO mail (mail_receiver, mail_content, mail_title, mail_sender) values (?, ?, ?, ?)', array($result[0]["char_id"], htmlspecialchars($_POST["content"]), $_POST["title"], $_SESSION["char_id"])); 
            }

            // else{
            //     $f3->set('mailerror', 'Podany użytkownik nie istnieje');
            // }
            
            echo \Template::instance()->render('mainpage.html');
        }
    }
    class home{
        function gethome($f3){
            if(empty($_SESSION["login"]) || empty($_SESSION["nickname"])){
                $f3->reroute('@login');
            }
            global $db;
            $user=new DB\SQL\Mapper($db,'characters');
            $user->load(array('char_id=?',$_SESSION["char_id"]));
            $_SESSION["currency"]=$user->currency;
            

            echo \Template::instance()->render('mainpage.html');
        }
        function profile($f3){
            $inv = new items;
            $inv->show_inventory();
            $inv->show_equipped();
            $inv->get_stats($_SESSION["char_id"]);
            
            echo \Template::instance()->render('profile.html');
        }
        function missions($f3){  
            global $db;
            if(empty($_SESSION["nickname"])){
                $f3->reroute('@login');
            }
            //if character has active mission
            if($result=$db->exec('SELECT mission_id, TIMESTAMPDIFF(SECOND,start_date,current_timestamp()) AS started_ago, duration_time, currency_reward, exp_reward, mission_description FROM missions LEFT JOIN mission_template on missions.mission_template_id = mission_template.mission_template_id WHERE char_id=? AND mission_active=1', $_SESSION["char_id"])){
                $f3->set('missions', false);
                //if active mission has ended
                
                if($result[0]["started_ago"]>$result[0]["duration_time"]){
                    $fight = new fight_module;
                    if($fight->fight($_SESSION['char_id'])){
                        $this->addexperience($result[0]["currency_reward"], $result[0]["exp_reward"]);
                    }
                    $f3->set('missionready', $result[0]);
                    $f3->set('mission_description', $result[0]["mission_description"]);
 

                        $db->exec('DELETE FROM missions WHERE char_id=?', $_SESSION["char_id"]);
                    // };
                }
                //if mission is not ended yet
                else{
                    $f3->set('missionready', false);
                    $f3->set('missionbox',$result[0]["duration_time"]-$result[0]["started_ago"]);
                }
            }
            //no active missions
            else{
                $f3->set('missions', true);

                //if missions are already generated
                if($result=$db->exec('SELECT char_id, currency_reward, exp_reward, duration_time, mission_name, mission_id FROM missions LEFT JOIN mission_template on missions.mission_template_id = mission_template.mission_template_id WHERE char_id=?',$_SESSION["char_id"])){
                    $f3->set('missionbox',$result);
                }
                //else generate new missions
                else{
                    $mission_templates = $db->exec('SELECT mission_template_id FROM mission_template ORDER BY rand() LIMIT 3');


                    for($i=0;$i<3;$i++){
                        // $duration_time=rand(1,20)*30;
                        $duration_time=1;
                        $currency_reward=round((($_SESSION["level"]*$_SESSION["level"]/10)+100)*$duration_time/100*(1+rand(0,1)));
                        $exp_reward=round((($_SESSION["level"]*$_SESSION["level"]/10)+100)*$duration_time/100*(1+rand(0,1)));
                        $exp_reward=1000;
                        $currency_reward=1000;

                        $db->exec('INSERT INTO missions (char_id, currency_reward, exp_reward, duration_time, mission_template_id, start_date, mission_active)
                        values (?, ?, ?, ?, ?, CURRENT_TIMESTAMP(), "0")', array($_SESSION["char_id"], $currency_reward, $exp_reward, $duration_time, $mission_templates[$i]["mission_template_id"]));
                    }

                    $f3->set('missionbox', $db->exec('SELECT char_id, currency_reward, exp_reward, duration_time, mission_name, mission_id FROM missions LEFT JOIN mission_template on missions.mission_template_id = mission_template.mission_template_id WHERE char_id=?',$_SESSION["char_id"]));
                }
            }
            echo \Template::instance()->render('missions.html');
        }
        function choosemission($f3){
            global $db;
            if(!empty($_SESSION["nickname"])){
                if($db->exec('SELECT * FROM missions WHERE mission_id=? AND char_id=?', array($_POST["activemission"], $_SESSION["char_id"]))){
                    $db->exec('UPDATE missions SET mission_active="1", start_date=current_timestamp() WHERE mission_id=?',$_POST["activemission"]);
                }
                $f3->reroute('@missions');
            }
            $f3->reroute('@login');
        }
        function addexperience($currency, $exp){
            global $db;
            $char=new DB\SQL\Mapper($db,'characters');
            $char->load(array('char_id=?',$_SESSION["char_id"]));
            $char->currency+=$currency;
            if($char->exp+$exp>=$char->exp_to_next_lv){
                $char->exp=$char->exp+$exp-$char->exp_to_next_lv;
                $char->level++;
                $char->exp_to_next_lv+=500;
            }
            else{
                $char->exp+=$exp;
            }
            $char->save();
            $_SESSION["currency"]=$char->currency;
            $_SESSION["exp"]=$char->exp;
            $_SESSION['level']=$char->level;
            $_SESSION['exp_to_next_lv']=$char->exp_to_next_lv;




        }
    }
    class login{
        function getlogin($f3){
            if(!empty($_SESSION["login"])){
                if(empty($_SESSION["nickname"])){
                    $this->getservers($f3);
                    echo \Template::instance()->render('servers.html');
                }
                else{
                    $f3->reroute('@home');
                }
            }
            else{
                echo \Template::instance()->render('login.html');
            }
        }
        function postlogin($f3){
            if (!empty($_SESSION["login"])){
                $f3->reroute('@login');
            }
            
            $f3->set('logintemplate', 'login.html');
            global $db;
            $user=new DB\SQL\Mapper($db,'accounts');
            if(!empty($_POST["login"])){
                $login=$_POST["login"];
                $password=md5($_POST["password"]);
                // $password=$_POST["password"];
    
                if(strlen($login)>$f3->get('max_login_len')){
                    $loginErr="login or password incorrect";
                }
                else{
                    if($user->load(array('login=?',$login))->login == $login && $user->load(array('login=?',$login))->password==$password){
                        $_SESSION["login"]=$login;
                        $_SESSION["user_id"]=$user->load(array('login=?',$login))->user_id;
    
                        $f3->set('servers', 'servers.html');
                        $f3->set('logintemplate', 'servers.html');
                        $this->getservers($f3);
                    }
                    else{
                        $loginErr="login or password incorrect";
                    }
                }
            }
            $f3->set('loginErr', $loginErr);
            echo \Template::instance()->render($f3->get('logintemplate'));
        }
        function getservers($f3){
            global $db;
            $sql="SELECT server_id, char_id, level, nickname 
            FROM (
                SELECT servers.server_id, char_id, level, nickname
                FROM servers
                JOIN characters ON servers.server_id = characters.server_id
                WHERE user_id=:id
        
                UNION
        
                SELECT servers.server_id, NULL AS char_id, NULL AS LEVEL, NULL AS nickname
                FROM servers
                LEFT JOIN characters ON servers.server_id = characters.server_id
                WHERE user_id!=:id or user_id IS NULL
            ) t
            GROUP BY server_id";
            $f3->set('result',$db->exec($sql, array(':id'=>$_SESSION["user_id"])));
        }
        function logintoserver($f3){
            global $db;
            if (empty($_SESSION["nickname"]) && !empty($_SESSION["login"]) && $db->exec('SELECT * FROM servers WHERE server_id=?', $_POST["serverno"])){
                if($result=$db->exec('SELECT char_id, nickname, race, icon, characters.server_id as server, level, currency, exp, exp_to_next_lv, char_class, class_name FROM servers LEFT JOIN characters ON servers.server_id = characters.server_id LEFT JOIN classes ON classes.class_id = characters.char_class WHERE servers.server_id = ? AND user_id = ?', array($_POST["serverno"],$_SESSION['user_id']))){
                    foreach($result[0] as $key => $value){
                        $_SESSION[$key]=$value;
                    }

                    $f3->reroute('@home');
                }
                else{
                    $_SESSION["server"]=$_POST["serverno"];
                    echo \Template::instance()->render('characterCreation.html');
                }
            }
            else{
                $f3->reroute('@login');
            }
        }
        function logout($f3){
            session_unset();
            $f3->reroute('@login');
        }

    }

    class register {
        function checkalphabet($string) {
            $alphabet=array("a","b","c","d","e","f","g","h","i","j","k","l","m","n","o","p","q","r","v","s","t","u","w","x","y","z","1","2","3","4","5","6","7","8","9","0");
            for ($i=0; $i<strlen($string); $i++) {
                if(!in_array(strtolower($string[$i]),$alphabet)) {
                    return false;
                }
            };
            return true;
        }
        function displayregister($f3) {
            echo \Template::instance()->render('register.html');
        }
        function inserting_data($f3) {
            //create mapper
            $f3->set('object_mapper',$user=new DB\SQL\Mapper($f3->get('conn'),'accounts')); 
            $login=$_POST['username'];
            $email=$_POST['email'];
            $utf = \UTF::instance();
            //checking if username and password is no empty
            if ((!$f3->get('POST.username')=="")&&(!$f3->get('POST.password')=="")) {
                //checking if username has permitted characters
                if($this->checkalphabet($f3->get('POST.username'))) {
                    $f3->set('error1',""); 
                }
                else {
                    $f3->set('error1',"Proszę podać poprawną nazwę użytkownika!"); 
                } 
                //checking if username is not too long
                if ((($utf->strlen($f3->get('POST.username')))<($f3->get('max_login_len')))&&($f3->get('error1')=="")) {
                    //checking if password is not too long
                    if ((($utf->strlen($f3->get('POST.password'))))>($f3->get('max_password_len'))) {
                        $f3->set('error2',"Hasło jest zbyt długie!");
                    }   else {
                            //insert password and username into database
                            if (!($f3->get('object_mapper')->load(array('login=?',$f3->get('POST.username'))))==$f3->get('POST.username')) {
                                $f3->get('object_mapper')->login=$f3->get('POST.username');
                                $f3->get('object_mapper')->password=md5($f3->get('POST.password')); 
                                $f3->get('object_mapper')->save(); 
                                $f3->reroute('@login'); 
                            }
                            else{
                                $f3->set('error5',"Użytkownik już istnieje!");
                            }
                    }
                }   else {
                        $f3->set('error3',"Nazwa użytkownika jest zbyt długa!"); 
                    };        
                
            } else {
                $f3->set('error4',"Proszę wypełnić wszystkie pola!");
            };  
            echo \Template::instance()->render('register.html');
            
        }
        function getCharacterIcons($f3) {
            // Return array of all possible (2 per class-race) paths of icons correlated with specific race and class 
            // Works with characterIcons.html template

            $class = $_GET['class'];
            $race = $_GET['race'];

            $result = array("ui/images/".$race."/".$class."1.jpg", "ui/images/".$race."/".$class."2.jpg");
            $f3->set('result', $result);

            echo \Template::instance()->render('characterIcons.html');
        }

        function postcreatechar($f3) {
            global $db;
            // $character_classes=array("informatyk", "mechatronik", "elektronik");
            $character_races=array("kobieta","karzel","czlowiek","zyd");
            
            // $f3->set('object_mapper_char', new DB\SQL\Mapper($f3->get('conn'),'characters'));

            if (!empty($_SESSION["login"]) && !empty($_SESSION["server"])) {
                $occupation = $f3->get('POST.occupation');
                $race = $f3->get('POST.race');
                $nickname = $f3->get('POST.nickname');
                $icon = $f3->get('POST.icon');
                $server = $f3->get('SESSION.server');
                $user_id = $f3->get('SESSION.user_id');
                
                if ($class_id = $db->exec('SELECT class_id FROM classes WHERE class_name=?', $occupation)[0] && !empty( $nickname ) && (in_array( $race, $character_races ))) {
                    $nick_already_used = $db->exec('SELECT char_id FROM characters WHERE nickname=? AND server_id=? LIMIT 1', array($nickname, $server));
                    // if nickname is already used
                    if ($nick_already_used) {

                        $f3->set('creating_error3', "Postać o takim nicku już istnieje!");
                    }
                    // check if user has already linked character on this server
                    elseif (empty($db->exec('SELECT char_id FROM characters WHERE server_id=? AND user_id=?', array($server, $user_id)))) {
                        
                        // check if nickname is valid
                        if( $this->checkalphabet($nickname) && strlen($nickname) < 16) { 
                            
                            // for future use
                            $exp_to_next_level = 200;
                            $attack = 10;
                            $defence = 10;
                            $vit = 100;
                            // 

                            $db->exec('INSERT INTO characters values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                                array(null, $user_id, $server, $class_id , $nickname, "0", "1", "0", $exp_to_next_level,
                                $attack, $defence, "10", "10", $vit, "10", "10", $race, $icon, null));
                            echo 'success';
                            }
                        else {
                            $f3->set('creating_error1', "Proszę wpisać poprawną nazwę postaci!");
                        }
                    } else {
                        $f3->set('creating_error4', "Twoja postać na tym serwerze już istnieje!");
                    }
                } else {
                    $f3->set('creating_error2', "Proszę uzupełnić wszystkie pola!");
                }

                // echo \Template::instance()->render('characters.html');
            }
            else{
                echo 'xd';
                // $f3->reroute("@login");
            }
        }
        function createchar($f3) {
            if (!empty($_SESSION["login"])) {
            
            }
        }
    }
?>