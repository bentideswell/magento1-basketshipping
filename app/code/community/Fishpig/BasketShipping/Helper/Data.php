<?php
/**
 * @category		Fishpig
 * @package		Fishpig_BasketShipping
 * @license		http://fishpig.co.uk/license.txt
 * @author		Ben Tideswell <help@fishpig.co.uk>
 */

class Fishpig_BasketShipping_Helper_Data extends Mage_Core_Helper_Abstract
{
	/**
	 * Automatically set the shipping method
	 * called via an observer
	 *
	 * @param Varien_Event_Observer $observer
	 * @return $this
	 */
	public function setShippingObserver(Varien_Event_Observer $observer)
	{
		// Fix one Idev_OneStepCheckout
#		Mage::app()->getStore()->setConfig('onestepcheckout/general/rewrite_checkout_links', false);
		
		$shipping = $this->_getShippingAddress();
		$billing = $this->_getBillingAddress();

		if (!$shipping->getCountryId()) {
			$shipping->setCountryId($this->_getDefaultShippingCountryId());
		}

		$billing->setCountryId($shipping->getCountryId())->save();

		if ($shipping->getShippingMethod()) {
			return $this;
		}
		
		$shipping->setCollectShippingRates(true)->collectShippingRates()->collectTotals();

		if ($_allRates = $shipping->getGroupedAllShippingRates()) {
			foreach($_allRates as $_rates) {
				foreach($_rates as $_rate) {
					$shipping->setShippingMethod($_rate->getCode());//->save();

					$this->_getQuote()->save();
					$this->_getSession()->resetCheckout();
					
					return $this;
				}
			}
		}

		// Backup technique
		$shipping->setCollectShippingRates(true)->collectShippingRates();
		$rates = $shipping->getShippingRatesCollection();
		$lowest = null;

		if (count($rates) > 0) {
			foreach($rates as $rate) {
				if (is_null($lowest)) {
					$lowest = $rate;
				}
				else if ($rate->getPrice() < $lowest->getPrice()) {
					$lowest = $rate;
				}
				else if ($rate->getPrice() === 0) {
					$lowest = $rate;
					break;
				}
			}
		}
		
		if (!is_null($lowest)) {
			$shipping->setShippingMethod($lowest->getCode());
			$this->_getQuote()->save();
			$this->_getSession()->resetCheckout();
		}
		
		return $this;
	}

	/**
	 * Setup the observers required to operate
	 *
	 * @param Varien_Event_Observer $observer
	 */
	public function setupObserversObserver(Varien_Event_Observer $observer)
	{
		$xml = array();
		$config = Mage::app()->getConfig();
		$events = array_keys($config->getNode('basketshipping/events')->asArray());
		
		$template = trim("
			<%s>
				<observers>
					<basketshipping>
						<type>singleton</type>
						<class>Fishpig_BasketShipping_Helper_Data</class>
						<method>setShippingObserver</method>
					</basketshipping>
				</observers>
			</%s>
		");

		foreach($events as $event) {
			$xml[] = sprintf($template, $event, $event);
		}
		
		$configNew = new Varien_Simplexml_Config();

		$configNew->loadString(
			sprintf('<config><frontend><events>%s</events></frontend></config>', implode("\n", $xml))
		);
		
		$config->extend($configNew);
		
		// Idev_OneStepCheckout fix
		if ($node = $config->getNode('frontend/events/sales_quote_collect_totals_before/observers/onestepcheckout_set_address_defaults')) {
			$node->type = 'helper';
			$node->class = get_class($this);
			$node->method = 'rewritedIdevOneStepCheckoutSetDefaults';
		}
		
		return $this;
	}

	/**
	 * Disables some functionality of Idev_OneStepCheckout that breaks this extension
	 * This shouldn't be necessary as Idev should include an on/off switch for this functionality
	 *
	 * @param Varien_Event_Observer $observer
	 * @return $this
	 */
	public function rewritedIdevOneStepCheckoutSetDefaults(Varien_Event_Observer $observer)
	{
		if ($presetDefaults = Mage::getSingleton('onestepcheckout/observers_presetDefaults')) {
			/**
			  * The setAddressDefaults method removes the 
			  * country and shipping method set via Basket Shipping
			  * so is commented out. This does not affect Idev_OneStepCheckout
			  **/
			//$presetDefaults->setAddressDefaults($observer);

			$presetDefaults->setShippingDefaults($observer);
			$presetDefaults->setPaymentDefaults($observer);
		}
		
		return $this;
	}

	/**
	 * Retrieve the current shipping ID
	 *
	 * @return int|null
	 */
	public function getCountryId()
	{
		return $this->_getShippingAddress()->getCountryId();
	}
	
	/**
	 * Retrieve the default shipping country ID
	 * this is taken from the default country set in General > Countries Options > Default Country
	 *
	 * @return string
	 */
	protected function _getDefaultShippingCountryId()
	{
		return ($code = $this->_getGeoIPCountryCode()) !== false
			? $code
			: Mage::getStoreConfig('general/country/default');
	}
	
	/**
	 * Retrieve the country code set by the GeoIP software
	 *
	 * @return false|string
	 */
	protected function _getGeoIPCountryCode()
	{
		if (!Mage::getStoreConfigFlag('basketshipping/geoip/enabled')) {
			return false;
		}
		
		if (($cookieName = trim(Mage::getStoreConfig('basketshipping/geoip/cookie'))) === '') {
			return false;
		}

		if (($cookie = Mage::getModel('core/cookie')->get($cookieName)) === false) {
			return false;		
		}
		
		try {
			$country = Mage::getModel('directory/country')->loadByCode($cookie);
		
			if ($country->getId()) {
				return $country->getId();
			}
		}
		catch (Exception $e) {}

		$countries = Mage::getResourceModel('directory/country_collection')
			->loadData()
			->toOptionArray(false);
			
		foreach($countries as $country) {
			if (strtolower($cookie) === strtolower($country['label'])) {
				return $country['value'];
			}
		}
		
		return false;
	}
	
	/**
	 * Retrieve the checkout session model
	 *
	 * @return Mage_Checkout_Model_Session
	 */
	protected function _getSession()
	{
		return Mage::getSingleton('checkout/session');
	}
	
	/**
	 * Retrieve the quote model
	 *
	 * @return Mage_Sales_Model_Quote
	 */
	protected function _getQuote()
	{
		return Mage::getSingleton('checkout/cart')->getQuote();
	}
	
	/**
	 * Retrieve the shipping address model
	 *
	 * @return
	 */
	protected function _getShippingAddress()
	{
		return $this->_getQuote()->getShippingAddress();
	}
	
	/**
	 * Retrieve the billing address model
	 *
	 * @return
	 */	
	protected function _getBillingAddress()
	{
		return $this->_getQuote()->getBillingAddress();
	}

	/**
	 * Determine whether BasketShipping is Enabled
	 *
	 * @return bool
	 */
	public function isEnabled()
	{
		return Mage::getStoreConfigFlag('basketshipping/settings/enabled');
	}
}
