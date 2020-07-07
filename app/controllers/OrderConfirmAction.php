<?php
namespace Controllers;
use \Models\CustomerDao;
use \Models\CustomerDto;
use \Models\DeliveryDao;
use \Models\DeliveryDto;
use \Models\ItemsDao;
use \Models\ItemsDto;
use \Models\DBParamException;
use \Models\NoRecordException;
use \Models\InvalidParamException;
use \Models\MyPDOException;
use \Config\Config;

class OrderConfirmAction{
        
    public function execute(){        

        $cmd = filter_input(INPUT_POST, 'cmd');
        
        if($cmd == "do_logout" ){
            $_SESSION['customer_id'] = null;
        }

        $customerDao = new CustomerDao();
        $deliveryDao = new DeliveryDao();
        
        /*====================================================================
         cart.phpで「レジに進む」ボタンが押された時の処理
        =====================================================================*/
        if($cmd == "order_confirm"){
            try{
                if(isset($_SESSION['cart']) && isset($_SESSION['availableForPurchase'])){

                    $var = 1;
                    $totalPrice = 0; 
                    $totalAmount = 0;
                    $totalQuantity = 0;
                    $tax = 0;

                    /*- カート情報及びcart.phpで選択した商品の点数をとりだし、セッションに格納 -*/
                    for($i = 0 ; $i<count($_SESSION["cart"]); $i++ ){
                        
                        $_SESSION["cart"][$i]['item_quantity'] = $_POST["cart{$var}"];
                        
                        /*- $_SESSION['order']に格納する値を計算-*/
                        $totalQuantity += $_SESSION["cart"][$i]['item_quantity']; 
                        $totalAmount += $_SESSION["cart"][$i]['item_price_with_tax'] * $_SESSION["cart"][$i]['item_quantity'];
                        $tax += $_SESSION["cart"][$i]['item_tax'] * $_SESSION["cart"][$i]['item_quantity'];
                        
                        $var++;
                    }
                    if($totalAmount >= Config::POSTAGEFREEPRICE){
                        $postage = 0;
                    }else{
                        $postage = Config::POSTAGE;
                    }

                    /*- 注文処理で使用するセッション変数の初期化 -*/
                    $_SESSION['pay_error'] = NULL;
                    $_SESSION['pay_type'] = NULL;
                    $_SESSION['payment_term'] = NULL;
                    $_SESSION['def_addr'] = NULL;
                    $_SESSION['delivery'] = NULL;

                    $_SESSION['order'] = array(
                        'total_quantity' => $totalQuantity,  
                        'total_amount' => $totalAmount,
                        'tax' => $tax,
                        'postage' => $postage
                    );
                    
                }else{
                    if(isset($_SESSION['cart'])){
                        $cart = print_r($_SESSION['cart'], true);
                    }else{
                        $cart = "nothing";   
                    }
                    if(isset($_SESSION['availableForPurchase'])){
                        $availableForPurchase = $_SESSION['availableForPurchase'];
                    }else{
                        $availableForPurchase = "nothing";   
                    }
                    throw new InvalidParamException('Invalid param for order_confirm:$cart='.$cart.'/$_SESSION["availableForPurchase"]='.$availableForPurchase);
                } 
            } catch(InvalidParamException $e){
                $e->handler($e);
            }
        }
        
        try{
            if(!isset($_SESSION['availableForPurchase'])){
                throw new InvalidParamException('Invalid param for order_confirm_pay_list:$_SESSION["availableForPurchase"]=nothing');
            }
        } catch(InvalidParamException $e){
            $e->handler($e);
        }
        
        /*——————————————————————————————————————————————————————————————
        　非ログイン状態の場合の処理
        ————————————————————————————————————————————————————————————————*/

        if(!isset($_SESSION["customer_id"])){
            $_SESSION['order_flag'] = "is";
            header("Location:/html/login.php");   
            exit();
        }else{
            $customerId = $_SESSION['customer_id'];   
        }
        
        if(!isset($_SESSION['order'])){
            header('Location:/html/cart.php');
            exit();
        }
        /*——————————————————————————————————————————————————————————————*/
        
        try{
            $customer = $customerDao->getCustomerById($customerId);
            if(!$customer->getDeliveryFlag()){    
                /*- customerテーブルの住所が配送先のデフォルトでなければ
                deliveryテーブルからデフォルト設定された住所を取得、セッションに格納 -*/
                $delivery = $deliveryDao->getDefDeliveryInfo($customerId);
                $this->saveDeliveryData($delivery);

            }else{
                $this->saveCustomerData($customer);
            }

        } catch(MyPDOException $e){
            $e->handler($e);

        }catch(DBParamException $e){
            $e->handler($e);
        }

        /*——————————————————————————————————————————————————————————————
         「配送先確定」ボタンがおされたときの処理
        ————————————————————————————————————————————————————————————————*/
        
        if($cmd == "del_comp"){
            $def_addr = filter_input(INPUT_POST, 'def_addr');
            $_SESSION['def_addr'] = $def_addr;
            
            if($def_addr != "customer") {
                try{
                    $delivery  = $deliveryDao->getDeliveryInfoById($customerId, $def_addr);
                    $this->saveDeliveryData($delivery);
                    
                } catch(MyPDOException $e){
                    $e->handler($e);

                }catch(DBParamException $e){
                    $e->handler($e);
                }
            }else{
                try{
                    $customer = $customerDao->getCustomerById($customerId); 
                    $this->saveCustomerData($customer);

               } catch(MyPDOException $e){
                $e->handler($e);

                }catch(DBParamException $e){
                    $e->handler($e);
                }
            }
        }
        
        /*——————————————————————————————————————————————————————————————
         「決済方法確定」ボタンがおされたときの処理
        ————————————————————————————————————————————————————————————————*/
        if($cmd == "pay_comp") {
            $payType = filter_input(INPUT_POST, 'pay_type');
            $_SESSION['pay_error'] = NULL;
            $_SESSION['payment_term'] = NULL;

            if(!$payType){   
                $_SESSION['pay_error']="is";
                header('Location:/html/order/order_pay_list.php');
                exit();   
            }else{
                $_SESSION['pay_type'] = $payType;

                if($payType == "1") {
                    $_SESSION['payment_term'] = "クレジットカード";
                }elseif($payType == "2") {
                    $_SESSION['payment_term'] = "代引き";
                }else{
                    $_SESSION['payment_term'] = "銀行振込";
                } 
            }
        }
        
        if(isset($_SESSION['purchase_error'])){

            try{
                for($i = 0 ; $i<count($_SESSION["cart"]); $i++ ){
                    //購入できない商品を特定し最新の商品ステータスに更新
                    if($_SESSION['purchase_error'] == $_SESSION["cart"][$i]['item_code']){
                        $itemCode = $_SESSION["cart"][$i]['item_code'];
                        $itemsDao = new ItemsDao();
                        $itemsDto = $itemsDao->getItemByItemCode($itemCode);
                        $_SESSION["cart"][$i]['item_status'] = $itemsDto->getItemStatus();
                    }
                }
            } catch(MyPDOException $e){
                $e->handler($e);

            }catch(DBParamException $e){
                $e->handler($e);
            }
        }
        /*——————————————————————————————————————————————————————————————*/
    }
    
    /*- DTOクラスで用意したgetterメソッド名は同じだが、念のためにcustomerテーブルとdeliveryテーブルとで分けて下記メソッドを用意。 -*/
    public function saveDeliveryData($delivery){
        $_SESSION['delivery'] = array(
            'name' => $delivery->getFullName(),
            'post' => $delivery->getPost(),
            'address' => $delivery->getAddress(),
            'tel' => $delivery->getTel()
        );
    }
    
    public function saveCustomerData($customer){
        $_SESSION['delivery'] = array(
            'name' => $customer->getFullName(),
            'post' => $customer->getPost(),
            'address' => $customer->getAddress(),
            'tel' => $customer->getTel()
        );
    }
}

?>    

   