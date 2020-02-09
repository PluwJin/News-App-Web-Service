<?php
ini_set('upload_max_filesize', '20M');
$user=getRealIpAddr();
$connect=new mysqli('localhost','root','','mydb');
$connect->set_charset("utf8");

if($connect->connect_error){
    die("Connection failed: " . $connect->connect_error);
}

if($_SERVER["REQUEST_METHOD"]=="POST"){
    //Web uygulamasından gelen post isteği ile haber ekleyecek kısım
    $header=addslashes($_POST["newsName"]);
    $type=$_POST["newsType"];
    $content=addslashes($_POST["newsContent"]);
    $releaseDate=$_POST["newsDate"];
    $newsImage=file_get_contents($_FILES["newsPic"]["tmp_name"]);
    $newsImage=base64_encode($newsImage);

    $query="INSERT INTO news (name,type,content,image,date) VALUES ('$header','$type','$content','$newsImage','$releaseDate')";
    if($connect->query($query)== TRUE){
        echo "New Record Created Successfully";
        $nNewsId=$connect->query("SELECT Max(id) as nid FROM news");
        $nNewsId=$nNewsId->fetch_assoc();
        pushNotify(stripslashes($header),stripslashes($content),$nNewsId["nid"]);
    }
    else{
        echo $query;
        echo "Error:"."<br>".$connect->error;
    }
}
if($_SERVER["REQUEST_METHOD"]=="GET"){
    //Appden gelen get isteği doğrultusunda veritabanında veri çeker ve response olarak dönecek

    if(isset($_GET['newsId'])){                                                   //Haber Detay Görüntüleme
         if(is_numeric($_GET['newsId'])){
             $newsId=$_GET['newsId'];
            
             $query="SELECT * FROM news WHERE id=$newsId";
             $newsContent=$connect->query($query)->fetch_assoc();                 //Sonuç İlşkili Diziye Çevirildi (Tek Satır)
             if($newsContent!=null){
                $isliked=isLike($newsId);
                $newsContent=array("liked" => $isliked["like"]) + $newsContent;
             }
             $newsAsJSON=json_encode($newsContent);                               //Sonuç JSON a çevrildi.
             echo "[".$newsAsJSON."]";                                                    //Haberi Bas                                                               
             $newView=$newsContent['views']+1;                                    //Görüntülenme Sayısını Bir Arttır
             $updateQuery="UPDATE news  SET views=$newView  WHERE id=$newsId";
             if($connect->query($updateQuery)){}
             else{
                 echo $connect->error;
            }
             
        }
    }

    else if(isset($_GET['newsType'])){                                              //Haber Kategori Seçme Veya Tüm Haberleri Görüntüleme (All)
            $newsType=$_GET['newsType'];
            if($newsType=="All")
                $query="SELECT * FROM news"; 
            else   
                $query="SELECT * FROM news WHERE type='$newsType'";
            $res=$connect->query($query);
            $newsContent= $res->fetch_all(MYSQLI_ASSOC);                            //Tüm Sonuçlar İki Boyutlu Assoc. Diziye Çevrildi.
            $newsAsJSON=json_encode($newsContent);
            echo $newsAsJSON;
       }
         else{
             echo "Invalid Parameter:".$_GET['newsId'];
         }

}

if($_SERVER["REQUEST_METHOD"]=="PUT"){
    $likes= file_get_contents("php://input"); 
                                            
    if(!empty($likes)){ 
         
                                                                 
        $params=explode(",",$likes);
        $usersSign=isLike($params[0]);
        echo $usersSign["like"]; 

        if($usersSign["like"]==null){
            if($params[1]=="like")
                $query="UPDATE news SET  `like`= `like`+1  WHERE id=$params[0]; INSERT INTO users VALUES ('$user', $params[0] ,'$params[1]'); ";
            else
                $query="UPDATE news SET  `dislike`= `dislike`+1  WHERE id=$params[0];INSERT INTO users VALUES ('$user', $params[0] ,'$params[1]');";
            if($connect->multi_query($query)){
                echo "True";
            }
            else{
                echo $connect->error;
                 echo false;
            }
        }
        else if($usersSign["like"]=="like"){
            if($params[1]=="like")
                $query="UPDATE news SET  `like`= `like`-1 WHERE id=$params[0];DELETE FROM users WHERE newsId=$params[0] AND id= '$user'; ";
            else
                $query="UPDATE news SET  `dislike`= `dislike`+1,`like`= `like`-1  WHERE id=$params[0];UPDATE users SET `like`='dislike' WHERE newsId=$params[0] AND id='$user';";
            if($connect->multi_query($query)){
                echo "True";
            }
            else{
                echo $connect->error;
                 echo false;
            }
        }
        else if($usersSign["like"]=="dislike"){
            if($params[1]=="like")
                $query="UPDATE news SET  `like`= `like`+1,`dislike`= `dislike`-1 WHERE id=$params[0];UPDATE users SET `like`='like' WHERE newsId=$params[0] AND id='$user';";
            else
                $query="UPDATE news SET  `dislike`= `dislike`-1 WHERE id=$params[0];DELETE FROM users WHERE newsId=$params[0] AND id= '$user'; ";
            if($connect->multi_query($query)){
                echo "True";
            }
            else{
                echo $connect->error;
                 echo false;
            }
        }
        
    }
}
function isLike($params){
    $user=$GLOBALS["user"];
    $userResult=$GLOBALS['connect']->query("SELECT `like` FROM users WHERE newsId=$params AND id='$user'");
    $usersSign["like"]="null";
    $temp=$userResult->fetch_assoc();
    if($temp!=null)
        $usersSign=$temp;
    
   
            


    return $usersSign;
}         
function getRealIpAddr()
{
    $ip=false;
    if (!empty($_SERVER['HTTP_CLIENT_IP']))   //check ip from share internet
    {
      $ip=$_SERVER['HTTP_CLIENT_IP'];
    }
    elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR']))   //to check ip is pass from proxy
    {
      $ip=$_SERVER['HTTP_X_FORWARDED_FOR'];
    }
    else
    {
      $ip=$_SERVER['REMOTE_ADDR'];
    }
    
    
    return $ip;
}

function pushNotify($title,$message,$newsId){
    $url="https://fcm.googleapis.com/fcm/send";
    $fields=array("to"=>"/topics/general","data"=>array("title"=>$title,"message"=>$message,"nid"=>$newsId));
    $headers=array("Authorization:key=**************************","Content-Type:application/json");
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt ($ch, CURLOPT_SSL_VERIFYHOST, 0);  
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
    $result = curl_exec($ch);           
    if ($result === FALSE) {
        die('Curl failed: ' . curl_error($ch));
    }
    curl_close($ch);
    return $result;
 }


  





?>