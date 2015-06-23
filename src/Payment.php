<?php

namespace OndraKoupil\Csob;

use \OndraKoupil\Tools\Strings;
use \OndraKoupil\Tools\Arrays;

/**
 * A Payment request.
 *
 * If you want to init new payment, you have to manually create one instance
 * of this class and fill its public properties with real information
 * about the order.
 */
class Payment {

	/**
	 * @ignore
	 * @var string
	 */
	protected $merchantId;

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
	 * @ignore
	 * @var number
	 */
	protected $totalAmount = 0;

	/**
	 * Currency of the transaction. Default value is "CZK".
	 * @var string
	 */
	public $currency;

	/**
	 * Should the payment be processed right on?
	 * See Wiki on ČSOB's github for more information.
	 *
	 * Default value is true.
	 *
	 * @var bool
	 */
	public $closePayment = true;

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
	 * @var string
	 * @see returnUrl
	 */
	public $returnMethod;

	/**
	 * @ignore
	 * @var array
	 */
	protected $cart = array();

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
	 * @ignore
	 * @var string
	 */
	protected $merchantData;

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
	 * @ignore
	 * @var string
	 */

	protected $dttm;

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
	private $fieldsInOrder = array(
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
		"description",
		"merchantData",
		"customerId",
		"language"
	);


	/**
	 *
	 * @param type $orderNo
	 */
	function __construct($orderNo, $merchantData = null, $customerId = null) {
		$this->orderNo = $orderNo;

		if ($merchantData) {
			$this->setMerchantData($merchantData);
		}

		if ($customerId) {
			$this->customerId = $customerId;
		}
	}

	/**
	 * Add one cart item.
	 *
	 * You are required to add one or two cart items (at least on API v1).
	 *
	 * Remember that $totalAmount must be given in hundreth of currency units
	 * (cents for USD or EUR, "halíře" for CZK)
	 *
	 * @param string $name Name that customer will see
	 * (will be automatically trimmed to 20 characters)
	 * @param number $quantity
	 * @param number $totalAmount Total price (total sum for all $quantity)
	 * @param string $description Aux description (trimmed to 40 chars max)
	 *
	 * @return Payment Fluent interface
	 *
	 * @throws \RuntimeException When more than 2nd cart item is to be added
	 * @throws \InvalidArgumentException When other argument is invalid
	 */
	function addCartItem($name, $quantity, $totalAmount, $description = "") {

		if (count($this->cart) >= 2) {
			throw new \RuntimeException("This version of banks's API supports only up to 2 cart items in single payment, you can't add any more items.");
		}

		if (!is_numeric($quantity) or $quantity < 1) {
			throw new \InvalidArgumentException("Invalid quantity: $quantity. It must be numeric and >= 1");
		}

		$name = Strings::shorten($name, 20, "", true, true);
		$description = Strings::shorten($description, 40, "");

		$this->cart[] = array(
			"name" => $name,
			"quantity" => $quantity,
			"amount" => $totalAmount,
			"description" => $description
		);

		return $this;
	}

	/**
	 * Set some arbitrary data you will receive back when customer returns
	 *
	 * @param string $data
	 * @param bool $alreadyEncoded True if the data is already encoded to Base64
	 *
	 * @return Payment Fluent interface
	 *
	 * @throws \InvalidArgumentException When the data is too long
	 */
	public function setMerchantData($data, $alreadyEncoded = false) {
		if (!$alreadyEncoded) {
			$data = base64_encode($data);
		}
		if (strlen($data) > 255) {
			throw new \InvalidArgumentException("Merchant data can not be longer than 255 characters after base64 encoding.");
		}
		$this->merchantData = $data;
		return $this;
	}

	/**
	 * Get back merchantData, decoded to original value.
	 *
	 * @return string
	 */
	public function getMerchantData() {
		if ($this->merchantData) {
			return base64_decode($this->merchantData);
		}
		return "";
	}

	/**
	 * After the payment has been saved using payment/init, you can
	 * get PayID from here.
	 *
	 * @return string
	 */
	public function getPayId() {
		return $this->foreignId;
	}

	/**
	 * Do not call this on your own. Really.
	 *
	 * @param string $id
	 */
	public function setPayId($id) {
		$this->foreignId = $id;
	}

	/**
	 * Validate and initialise properties. This method is called
	 * automatically in proper time, you never have to call it on your own.
	 *
	 * @param Config $config
	 * @throws \RuntimeException
	 * @return Payment Fluent interface
	 *
	 * @ignore
	 */
	function checkAndPrepare(Config $config) {
		$this->merchantId = $config->merchantId;

		$this->dttm = date(Client::DATE_FORMAT);

		if (!$this->payOperation) {
			$this->payOperation = "payment";
		}

		if (!$this->payMethod) {
			$this->payMethod = "card";
		}

		if (!$this->currency) {
			$this->currency = "CZK";
		}

		if (!$this->language) {
			$this->language = "CZ";
		}

		if ($this->closePayment === null) {
			$this->closePayment = true;
		}

		if (!$this->returnUrl) {
			$this->returnUrl = $config->returnUrl;
		}
		if (!$this->returnUrl) {
			throw new \RuntimeException("A ReturnUrl must be set - either by setting \$returnUrl property, or by specifying it in Config.");
		}

		if (!$this->returnMethod) {
			$this->returnMethod = $config->returnMethod;
		}

		if (!$this->description) {
			$this->description = $config->shopName.", ".$this->orderNo;
		}
		$this->description = Strings::shorten($this->description, 240, "...");

		$this->customerId = Strings::shorten($this->customerId, 50, "", true, true);

		if (!$this->cart) {
			throw new \RuntimeException("Cart is empty. Please add one or two items into cart using addCartItem() method.");
		}

		if (!$this->orderNo or !preg_match('~^[0-9]{1,10}$~', $this->orderNo)) {
			throw new \RuntimeException("Invalid orderNo - it must be a non-empty numeric value, 10 characters max.");
		}

		$sumOfItems = array_sum(Arrays::transform($this->cart, true, "amount"));
		$this->totalAmount = $sumOfItems;

		return $this;
	}

	/**
	 * Add signature and export to array. This method is called automatically
	 * and you don't need to call is on your own.
	 *
	 * @param Config $config
	 * @return array
	 *
	 * @ignore
	 */
	function signAndExport(Config $config) {
		$arr = array();

		foreach($this->fieldsInOrder as $f) {
			$val = $this->$f;
			if ($val === null) {
				$val = "";
			}
			$arr[$f] = $val;
		}

		$stringToSign = $this->getSignatureString();

		$signed = Crypto::signString($stringToSign, $config->privateKeyFile, $config->privateKeyPassword);
		$arr["signature"] = $signed;

		return $arr;
	}

	/**
	 * Convert to string that serves as base for signing.
	 * @return string
	 * @ignore
	 */
	function getSignatureString() {
		$parts = array();

		foreach($this->fieldsInOrder as $f) {
			$val = $this->$f;
			if ($val === null) {
				$val = "";
			}
			elseif (is_bool($val)) {
				if ($val) {
					$val = "true";
				} else {
					$val = "false";
				}
			} elseif (is_array($val)) {
				// There are never more than 2 levels, we don't need recursive walk
				$valParts = array();
				foreach($val as $v) {
					if (is_scalar($v)) {
						$valParts[] = $v;
					} else {
						$valParts[] = implode("|", $v);
					}
				}
				$val = implode("|", $valParts);
			}
			$parts[] = $val;
		}

		return implode("|", $parts);
	}


}
