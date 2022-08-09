<?php

namespace OndraKoupil\Csob;

use OndraKoupil\Tools\Arrays;
use OndraKoupil\Tools\Strings;

/**
 * A payment request.
 *
 * To init new payment, you need to create an instance
 * of this class and fill its properties with real information
 * from the order.
 */
class Payment
{

    /**
     * Běžná platba
     */
    const OPERATION_PAYMENT = "payment";

    /**
     * Opakovaná platba
     *
     * @deprecated Deprecated since eAPI 1.7 - use one click payments
     */
    const OPERATION_RECURRENT = "recurrentPayment";

    /**
     * Platba na klik
     */
    const OPERATION_ONE_CLICK = "oneclickPayment";

    /**
     * Number of your order, a string of 1 to 10 numbers
     * (this is basically the Variable symbol).
     *
     * This is the only one mandatory value you need to supply.
     *
     * @var string
     */
    public $orderNo;

    /**
     * Currency of the transaction. Default value is "CZK".
     *
     * @var string
     */
    public $currency;

    /**
     * Should the payment be processed right on?
     * See Wiki on ČSOB's github for more information.
     *
     * If not set, value from Config us used (true by default).
     *
     * @var bool|null
     */
    public $closePayment = null;

    /**
     * Return URL to send your customers back to.
     *
     * You need to specify this only if you don't want to use the default
     * URL from your Config. Leave empty to use the default one.
     *
     * @var string
     */
    public $returnUrl;

    /**
     * Return method. Leave empty to use the default one.
     *
     * @var string
     * @see returnUrl
     */
    public $returnMethod;

    /**
     * Description of the order that will be shown to customer during payment
     * process.
     *
     * Leave empty to use your e-shop's name as given in Config.
     *
     * @var string
     */
    public $description;

    /**
     * Your customer's ID (e-mail, number, whatever...)
     *
     * Leave empty if you don't want to use some features relying on knowing
     * customer ID.
     *
     * @var string
     */
    public $customerId;

    /**
     * Language of the gateway. Default is "CZ".
     *
     * See wiki on ČSOB's Github for other values, they are not the same
     * as standard ISO language codes.
     *
     * @var string
     */
    public $language;

    /**
     * payOperation value. Leave empty to use the default
     * (and the only one valid) value.
     *
     * Using API v1, you can ignore this.
     *
     * @var string
     */
    public $payOperation;

    /**
     * payMethod value. Leave empty to use the default
     * (and the only one valid) value.
     *
     * Using API v1, you can ignore this.
     *
     * @var string
     */
    public $payMethod;

    /**
     * Lifetime of the transaction in seconds. Number from 300 to 1800.
     *
     * @var int
     */
    public $ttlSec;

    /**
     * Version of logo.
     *
     * @var int
     */
    public $logoVersion;

    /**
     * Color version
     *
     * @var int
     */
    public $colorSchemeVersion;

    /**
     * @ignore
     * @var string
     */
    protected $merchantId;

    /**
     * @ignore
     * @var number
     */
    protected $totalAmount = 0;

    /**
     * @ignore
     * @var array
     */
    protected $cart = [];

    /**
     * @var array
     */
    protected $customer = [];

    /**
     * @var array
     */
    protected $order = [];

    /**
     * @ignore
     * @var string
     */
    protected $merchantData;

    /**
     * @ignore
     * @var string
     */

    protected $dttm;

    /**
     * The PayID value that you will need fo call other methods.
     * It is given to your payment by bank.
     *
     * @var string
     * @see getPayId
     */
    protected $foreignId;

    /**
     * @var array
     * @ignore
     */
    private $fieldsInOrder = [
        "merchantId",
        "orderNo",
        "dttm",
        "payOperation",
        "payMethod",
        "totalAmount",
        "currency",
        "closePayment",
        "returnUrl",
        "returnMethod",
        "cart",
        "customer",
        "order",
        "description",
        "merchantData",
        "customerId",
        "language",
        "ttlSec",
    ];

    private $auxFieldsInOrder = [
        "logoVersion",
        "colorSchemeVersion",
    ];

    /**
     * @param string $orderNo
     * @param mixed $merchantData
     * @param string $customerId
     * @param bool|null $oneClickPayment
     */
    public function __construct($orderNo = '', $merchantData = null, $customerId = null, $oneClickPayment = null)
    {
        $this->orderNo = $orderNo;

        if ($merchantData) {
            $this->setMerchantData($merchantData);
        }

        if ($customerId) {
            $this->customerId = $customerId;
        }

        if ($oneClickPayment !== null) {
            $this->setOneClickPayment($oneClickPayment);
        }
    }

    /**
     * Mark this payment as one-click payment template
     *
     * Basically, this is a lazy method for setting $payOperation to OPERATION_ONE_CLICK
     *
     * @param bool $oneClick
     *
     * @return $this
     */
    public function setOneClickPayment($oneClick = true)
    {
        $this->payOperation = $oneClick ? self::OPERATION_ONE_CLICK : self::OPERATION_PAYMENT;

        return $this;
    }

    /**
     * Add one cart item.
     *
     * You are required to add one or two cart items (at least on API v1).
     *
     * Remember that $totalAmount must be given in **hundredth of currency units**
     * (cents for USD or EUR, "halíře" for CZK)
     *
     * @param string $name Name that customer will see
     * (will be automatically trimmed to 20 characters)
     * @param number $quantity
     * @param number $totalAmount Total price (total sum for all $quantity),
     * in **hundredths** of currency unit
     * @param string $description Aux description (trimmed to 40 chars max)
     *
     * @return Payment Fluent interface
     *
     * @throws Exception When more than 2nd cart item is to be added or other argument is invalid
     */
    public function addCartItem($name, $quantity, $totalAmount, $description = "")
    {
        if (count($this->cart) >= 2) {
            throw new Exception("This version of banks's API supports only up to 2 cart items in single payment, you can't add any more items.");
        }

        if (!is_numeric($quantity) || $quantity < 1) {
            throw new Exception("Invalid quantity: $quantity. It must be numeric and >= 1");
        }

        $name = trim(Strings::shorten($name, 20, "", true, true));
        $description = trim(Strings::shorten($description, 40, "", true, true));

        $this->cart[] = [
            "name" => $name,
            "quantity" => $quantity,
            "amount" => (int) round($totalAmount),
            "description" => $description,
        ];

        return $this;
    }

    /**
     * @param string|null $name
     * @param string|null $email
     * @param string|null $homePhone
     * @param string|null $workPhone
     * @param string|null $mobilePhone
     * @param array|null $accountData
     * @param array|null $loginData
     *
     * @return void
     * @see https://github.com/csob/platebnibrana/wiki/Dodate%C4%8Dn%C3%A1-data-o-n%C3%A1kupu
     */
    public function setCustomerData(?string $name, ?string $email = null, ?string $homePhone = null, ?string $workPhone = null, ?string $mobilePhone = null, ?array $accountData = null, ?array $loginData = null): void
    {
        $homePhone = $this->filterPhone($homePhone);
        $workPhone = $this->filterPhone($workPhone);
        $mobilePhone = $this->filterPhone($mobilePhone);

        $this->customer = array_filter([
            "name" => Strings::shorten(trim($name), 45, "", true, true),
            "email" => Strings::shorten(trim($email), 100, "", true, true),
            "homePhone" => $homePhone,
            "workPhone" => $workPhone,
            "mobilePhone" => $mobilePhone,
            "account" => $accountData,
            "login" => $loginData,
        ]);
    }

    private function filterPhone(?string $phone): string
    {
        if (null === $phone || '' === $phone) {
            return '';
        }

        if (!preg_match("~^(\+|00|)\d{1,3}\.[0-9 ]{3,}$~", $phone)) {
            return '';
        }

        return $phone;
    }

    /**
     * @return void
     * @see https://github.com/csob/platebnibrana/wiki/Dodate%C4%8Dn%C3%A1-data-o-n%C3%A1kupu
     */
    public function setOrderData(array $billing, bool $addressMatch, array $shipping = [], ?string $type = 'purchase'): void
    {
        $this->order = array_filter([
            "type" => $type,
            "addressMatch" => $addressMatch,
            "billing" => $this->getAddressData($billing),
            "shipping" => count($shipping) > 0 ? $this->getAddressData($shipping) : [],
        ]);
    }

    private function getAddressData(?array $data): array
    {
        $country = $data['country'] ?? null;
        $address = $data['address1'] ?? null;
        $city = $data['city'] ?? null;
        $zip = $data['zip'] ?? null;

        if (null === $country || null === $address || null === $city || null === $zip || !preg_match("/^[A-Z]{3}$/i", $country)) {
            throw new Exception("Invalid or missing mandatory address fields (address1, city, zip, country)");
        }

        return [
            "address1" => Strings::shorten($address, 50, "", true, true),
            "city" => Strings::shorten($city, 50, "", true, true),
            "zip" => Strings::shorten($zip, 16, "", true, true),
            "country" => Strings::shorten(strtoupper($country), 3, "", true, true),
        ];
    }

    public function getCustomer(): array
    {
        return $this->customer;
    }

    public function getOrder(): array
    {
        return $this->order;
    }

    /**
     * Get back merchantData, decoded to original value.
     *
     * @return string
     */
    public function getMerchantData()
    {
        if ($this->merchantData) {
            return base64_decode($this->merchantData);
        }

        return "";
    }

    /**
     * Set some arbitrary data you will receive back when customer returns
     *
     * @param string $data
     * @param bool $alreadyEncoded True if given $data is already encoded to Base64
     *
     * @return Payment Fluent interface
     *
     * @throws Exception When the data is too long and can't be encoded.
     */
    public function setMerchantData($data, $alreadyEncoded = false)
    {
        if (!$alreadyEncoded) {
            $data = base64_encode($data);
        }
        if (strlen($data) > 255) {
            throw new Exception("Merchant data can not be longer than 255 characters after base64 encoding.");
        }
        $this->merchantData = $data;

        return $this;
    }

    /**
     * Get back MerchantData encoded as base64.
     *
     * @return string
     */
    public function getMerchantDataEncoded()
    {
        return $this->merchantData ?: '';
    }

    /**
     * After the payment has been saved using payment/init, you can
     * get PayID from here.
     *
     * @return string
     */
    public function getPayId()
    {
        return $this->foreignId;
    }

    /**
     * Returns sum of all cart items in **hundreths** of base currency unit.
     *
     * @return number
     */
    public function getTotalAmount()
    {
        $sumOfItems = array_sum(Arrays::transform($this->cart, true, "amount"));
        $this->totalAmount = $sumOfItems;

        return $this->totalAmount;
    }

    /**
     * Cart items as array.
     *
     * @return array
     */
    public function getCart()
    {
        return $this->cart;
    }

    /**
     * Do not call this on your own. Really.
     *
     * @param string $id
     */
    public function setPayId($id)
    {
        $this->foreignId = $id;
    }

    /**
     * Mark this payment as a template for recurrent payments.
     *
     * Basically, this is a lazy method for setting $payOperation to OPERATION_RECURRENT.
     *
     * @deprecated Deprecated and replaced by setOneClickPayment
     *
     * @param bool $recurrent
     *
     * @return Payment
     */
    public function setRecurrentPayment($recurrent = true)
    {
        $this->payOperation = $recurrent ? self::OPERATION_RECURRENT : self::OPERATION_PAYMENT;
        trigger_error('setRecurrentPayment() is deprecated, use setOneClickPayment() instead.', E_USER_DEPRECATED);

        return $this;
    }

    /**
     * Validate and initialise properties. This method is called
     * automatically in proper time, you never have to call it on your own.
     *
     * @param Config $config
     *
     * @return Payment Fluent interface
     *
     * @throws Exception
     * @ignore
     */
    public function checkAndPrepare(Config $config)
    {
        $this->merchantId = $config->merchantId;

        $this->dttm = date(Client::DATE_FORMAT);

        if (!$this->payOperation) {
            $this->payOperation = self::OPERATION_PAYMENT;
        }

        if (!$this->payMethod) {
            $this->payMethod = "card";
        }

        if (!$this->currency) {
            $this->currency = "CZK";
        }

        if (!$this->language) {
            $this->language = "cs";
        }

        if (!$this->ttlSec || false === is_numeric($this->ttlSec)) {
            $this->ttlSec = 1800;
        }

        if ($this->closePayment === null) {
            $this->closePayment = $config->closePayment ? true : false;
        }

        if (!$this->returnUrl) {
            $this->returnUrl = $config->returnUrl;
        }
        if (!$this->returnUrl) {
            throw new Exception("A ReturnUrl must be set - either by setting \$returnUrl property, or by specifying it in Config.");
        }

        if (!$this->returnMethod) {
            $this->returnMethod = $config->returnMethod;
        }

        if (!$this->description) {
            $this->description = $config->shopName . ", " . $this->orderNo;
        }
        $this->description = Strings::shorten($this->description, 240, "...");

        $this->customerId = Strings::shorten($this->customerId, 50, "", true, true);

        if (!$this->cart) {
            throw new Exception("Cart is empty. Please add one or two items into cart using addCartItem() method.");
        }

        if (!$this->orderNo or !preg_match('~^[0-9]{1,10}$~', $this->orderNo)) {
            throw new Exception("Invalid orderNo - it must be a non-empty numeric value, 10 characters max.");
        }

        $sumOfItems = array_sum(Arrays::transform($this->cart, true, "amount"));
        $this->totalAmount = $sumOfItems;

        return $this;
    }

    /**
     * Add signature and export to array. This method is called automatically
     * and you don't need to call is on your own.
     *
     * @param Client $client
     *
     * @return array
     *
     * @ignore
     */
    public function signAndExport(Client $client): array
    {
        $arr = [];

        $config = $client->getConfig();

        $fieldNames = $this->fieldsInOrder;
        if ($client->getConfig()->queryApiVersion('1.8')) {
            // Version 1.8 omitted $description parameter
            $fieldNames = Arrays::deleteValue($fieldNames, 'description');
        }

        foreach ($fieldNames as $f) {
            $val = $this->{$f};
            if ($val === null) {
                $val = "";
            }

            if (is_array($val) && 0 === count($val)) {
                continue;
            }

            $arr[$f] = $val;
        }

        foreach ($this->auxFieldsInOrder as $f) {
            $val = $this->{$f};
            if ($val !== null) {
                $arr[$f] = $val;
            }
        }

        $stringToSign = $this->getSignatureString($client);

        $client->writeToTraceLog('Signing payment request, base for the signature:' . "\n" . $stringToSign);

        $signed = Crypto::signString($stringToSign, $config->privateKeyFile, $config->privateKeyPassword, $client->getConfig()->getHashMethod());
        $arr["signature"] = $signed;

        return $arr;
    }

    /**
     * Convert to string that serves as base for signing.
     *
     * @param Client $client
     */
    private function getSignatureString(Client $client): string
    {
        $parts = [];

        $fieldNames = $this->fieldsInOrder;
        if ($client->getConfig()->queryApiVersion('1.8')) {
            // Version 1.8 omitted $description parameter
            $fieldNames = Arrays::deleteValue($fieldNames, 'description');
        }

        foreach ($fieldNames as $f) {
            $values[$f] = $this->$f;
        }

        foreach ($this->auxFieldsInOrder as $f) {
            $val = $this->$f;

            if ($val !== null) {
                $values[$f] = $val;
            }
        }

        return Crypto::createSignatureBaseFromArray($values);
    }
}
