<?php
namespace Controllers;
use \Models\CustomerDao;
use \Models\CustomersDto;
use \Models\CommonValidator;
use \Models\OriginalException;
use \Config\Config;

class MyPageUpdateAction {

    private $customerDto;
    
    private $lastNameError = false;
    private $firstNameError = false;
    private $rubyLastNameError = false;
    private $rubyFirstNameError = false;
    private $address01Error = false;
    private $address02Error = false;
    private $address03Error = false;
    private $address04Error = false;
    private $address05Error = false;
    private $address06Error = false;
    private $telError = false;
    private $mailError = false;
    private $passwordError = false;
    private $passwordConfirmError = false;
    
    public function execute(){
        
        $cmd = filter_input(INPUT_POST, 'cmd');
        
        if($cmd == "do_logout" ){
            $_SESSION['customer_id'] = null;
        }
        
        if(!isset($_SESSION["customer_id"])){
            header("Location:/html/login.php");   
            exit();
        }else{
            $customerId = $_SESSION['customer_id'];   
        }
        
        
        //order_delivery_list.phpからきた場合
        if($cmd == "from_order"){
            $_SESSION['from_order_flag']= "is";   
        }

        $customerDao = new CustomerDao();
        
        try{
            $this->customerDto = $customerDao->getCustomerById($customerId);
        } catch(\PDOException $e){
            Config::outputLog($e->getCode(), $e->getMessage(), $e->getTraceAsString());;
            header('Content-Type: text/plain; charset=UTF-8', true, 500);
            die('エラー:データベースの処理に失敗しました。');

        }catch(OriginalException $e){
            Config::outputLog($e->getCode(), $e->getMessage(), $e->getTraceAsString());
            header('Content-Type: text/plain; charset=UTF-8', true, 400);
            die('エラー:'.$e->getMessage());
        }

        
        if(isset($_POST['cmd']) && $_POST['cmd']=="confirm"){
            
            $validator = new CommonValidator();
            
            $lastName = filter_input(INPUT_POST, 'last_name');
            $firstName = filter_input(INPUT_POST, 'first_name');
            $rubyLastName = filter_input(INPUT_POST, 'ruby_last_name');
            $rubyFirstName = filter_input(INPUT_POST, 'ruby_first_name');
            $address01 = filter_input(INPUT_POST, 'address01');
            $address02 = filter_input(INPUT_POST, 'address02');
            $address03 = filter_input(INPUT_POST, 'address03');
            $address04 = filter_input(INPUT_POST, 'address04');
            $address05 = filter_input(INPUT_POST, 'address05');
            $address06 = filter_input(INPUT_POST, 'address06');
            $tel = filter_input(INPUT_POST, 'tel');
            $mail = filter_input(INPUT_POST, 'mail');
            $password = filter_input(INPUT_POST, 'password');
            $passwordConfirm = filter_input(INPUT_POST, 'password_confirm');

            //パスワード、パスワード確認ともに入力がなければログイン時にセットしたクッキー値を格納しバリデーションを通す。(変更なしとみなす)
            if(!$password && !$passwordConfirm){
                $password = $_COOKIE['password'];
                $passwordConfirm = $_COOKIE['password'];
            }else{
                $_SESSION['password_input'] = "is";
            }

            $_SESSION['update'] = array(
             'last_name' => $lastName,
             'first_name' => $firstName,
             'ruby_last_name' => $rubyLastName,
             'ruby_first_name' => $rubyFirstName,
             'address01' => $address01,
             'address02' => $address02,
             'address03' => $address03,
             'address04' => $address04,
             'address05' => $address05,
             'address06' => $address06,
             'tel' => $tel,
             'mail' => $mail,
             'password' => $password,
             'password_confirm' => $passwordConfirm
            );

            $key = "氏名(性)";
            $this->lastNameError = $validator->fullWidthValidation($key, $lastName);

            $key = "氏名(名)";
            $this->firstNameError = $validator->fullWidthValidation($key, $firstName);

            $key = "氏名(セイ)";
            $this->rubyLastNameError = $validator->rubyValidation($key, $rubyLastName);

            $key = "氏名(メイ)";
            $this->rubyFirstNameError = $validator->rubyValidation($key, $rubyFirstName);

            $key = "郵便番号(3ケタ)";
            $this->address01Error = $validator->firstZipCodeValidation($key, $address01);

            $key = "郵便番号(4ケタ)";
            $this->address02Error  = $validator->lastZipCodeValidation($key, $address02);

            $key="都道府県";
            $this->address03Error = $validator->requireCheck($key, $address03);

            $key="市区町村";
            $this->address04Error = $validator->requireCheck($key, $address04);

            $key="番地";
            $this->address05Error = $validator->requireCheck($key, $address05);

            $key="メールアドレス";
            $this->mailError = $validator->mailValidation($key, $mail);
            
            if(!$this->mailError){
                $ExistingMail = $this->customerDto->getMail();
                try{
                    $this->mailError = $validator->checkMail($mail, $ExistingMail);
                    
                } catch(\PDOException $e){
                    Config::outputLog($e->getCode(), $e->getMessage(), $e->getTraceAsString());;
                    header('Content-Type: text/plain; charset=UTF-8', true, 500);
                    die('エラー:データベースの処理に失敗しました。');
                }
            }

            $key="電話番号";
            $this->telError = $validator->telValidation($key, $tel);

            $key="パスワード";
            $this->passwordError = $validator->passValidation($key, $password);

            $key="パスワード(再確認)";
            $this->passwordConfirmError = $validator->passConfirmValidation($key, $passwordConfirm, $password);

            if($validator->getResult()) {
                $_SESSION['update_data'] = "clear";
                header('Location:/html/mypage/mypage_update_confirm.php');
                exit();
            }
        }
    }
    
    public function getLastNameError(){
        return $this->lastNameError;   
    }
    
    public function getFirstNameError(){
        return $this->firstNameError;   
    }
    
    public function getRubyLastNameError(){
        return $this->rubyLastNameError;   
    }
    
    public function getRubyFirstNameError(){
        return $this->rubyFirstNameError;   
    }
    
    public function getAddress01Error(){
        return $this->address01Error;   
    }
    
    public function getAddress02Error(){
        return $this->address02Error;   
    }
   
    public function getAddress03Error(){
        return $this->address03Error;   
    }
    
    public function getAddress04Error(){
        return $this->address04Error;   
    }
    
    public function getAddress05Error(){
        return $this->address05Error;   
    }
    
    public function getAddress06Error(){
        return $this->address06Error;   
    }
    
    public function getTelError(){
        return $this->telError;   
    }
    
    public function getMailError(){
        return $this->mailError;   
    }
    
    public function getPasswordError(){
        return $this->passwordError;   
    }
    
    public function getPasswordConfirmError(){
        return $this->passwordConfirmError;   
    }
    
    /** @return CustomerDto */
    public function getCustomerDto(){
        return $this->customerDto;   
    }
}
?>