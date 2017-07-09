<?php
/**
 * @category		Fishpig
 * @package		Fishpig_BasketShipping
 * @license		http://fishpig.co.uk/license.txt
 * @author		Ben Tideswell <help@fishpig.co.uk>
 */

class Fishpig_BasketShipping_Block_Total_Shipping extends Mage_Tax_Block_Checkout_Shipping
{
	/**
	 * Cache for directory block
	 * This is used to quickly generate the country HTML select
	 *
	 * @var Mage_Directory_Block_Data
	 */
	protected $_directoryDataBlock = null;
	   
	/**
	 * If module enabled, set the correct template
	 *
	 * @return void
	 */
	 protected function _construct()
	 {
		 if (Mage::helper('basketshipping')->isEnabled()) {
			 $this->_template = 'basket-shipping/total/shipping.phtml';
		 }
		 
		 return parent::_construct();
	 }
	 
	 /**
	  * Get the form URL
	  *
	  * @return string
	  */
	 public function getFormActionUrl()
	 {
		 return $this->getUrl('checkout/cart/estimatePost', array(
		 	'_secure' => Mage::app()->getStore()->isCurrentlySecure())
		 );
	 }

	/**
	 * Retrieve the current shipping price
	 *
	 * @return string
	 */
	public function getCurrentShippingPrice()
	{
		if ($this->displayBoth() || $this->displayIncludeTax()) {
			return $this->formatPrice($this->getShippingIncludeTax());
		}
		
		return $this->formatPrice($this->getShippingExcludeTax());
	}
	
	/**
	 * Determine whether it is necessary to show the shipping options
	 * as a select
	 *
	 * @return bool
	 */
	public function canShowOptions()
	{
		return $this->getValidShippingOptionCount() > 1;
	}

	/**
	 * Retrieve the number of valid shipping options
	 *
	 * @return int
	 */	
	public function getValidShippingOptionCount()
	{
		if (!$this->hasValidShippingOptionCount()) {
			$it = 0;
			
			foreach($this->getEstimateRates() as $code => $_rates) {
				foreach($_rates as $_rate) {
					if (!$_rate->getErrorMessage()) {
						++$it;
					}	
				}
			}
			
			$this->setValidShippingOptionCount($it);
		}
		
		return $this->_getData('valid_shipping_option_count');
	}

	/**
	 * Retrieve an option label for a rate
	 *
	 * @param $_rate
	 * @return string
	 */
	public function getMethodOptionLabel($_rate)
	{
		$label = array($_rate->getMethodTitle());
		$helper = Mage::helper('tax');
		
		$priceIncludingTax = $this->formatPrice($helper->getShippingPrice($_rate->getPrice(), true));
		$priceExcludingTax = $this->formatPrice($helper->getShippingPrice($_rate->getPrice(), false));

		if ($this->getValidShippingOptionCount() > 1) {
			if ($this->displayBoth()) {
				$label[] = $priceExcludingTax;
				
				if ($_rate->getPrice() > 0) {
					$label[] = "(" . $priceIncludingTax . ' ' . $helper->__('Incl. Tax') . ')';
				}
			}
			else if ($this->displayIncludeTax()) {
				$label[] = $priceIncludingTax;
			}
			else {
				$label[] = $priceExcludingTax;
			}
		}
		
		/*
			$_excl = $this->getShippingPrice($_rate->getPrice(), $this->helper('tax')->displayShippingPriceIncludingTax());
			$_incl = $this->getShippingPrice($_rate->getPrice(), true);
			
			$label = $_excl;
	
			if ($this->helper('tax')->displayShippingBothPrices() && $_incl != $_excl) {
				$label .= ' (' . $this->__('Incl. Tax') . ' ' . $_incl . ')';
			}
		*/
                                    
		return implode(' ', $label);
	}

	/**
	 * Determine whether $_rate is the current rate
	 *
	 * @param $_rate
	 * @return bool
	 */
	public function isCurrentRate($_rate)
	{
		return $_rate->getCode()===$this->getShippingBlock()->getAddressShippingMethod();
	}
	
	/**
	 * Retrieve the shipping block
	 * This stops us redfining the methods declared there
	 *
	 * @return Mage_Checkout_Block_Cart_Shipping
	 */    
	public function getShippingBlock()
	{
		if (!$this->hasShippingBlock()) {
			$this->setShippingBlock($this->getLayout()->createBlock('checkout/cart_shipping'));
		}
		
		return $this->_getData('shipping_block');
	}
	
	/**
	 * Retrieve an array of the estimate rates
	 *
	 * @return array
	 */	
	public function getEstimateRates()
	{
		return $this->getShippingBlock()->getEstimateRates();
	}
	
	/**
	 * Format a price
	 *
	 * @param mixed
	 * @return string
	 */
	public function formatPrice($price)
	{
		return $this->helper('checkout')->formatPrice(
			Mage::app()->getStore(true)->convertPrice($price)
		);
	}
	
	/**
	 * Get the shipping carrier's name
	 *
	 * @param string $carrierCode
	 * @return string
	*/
	public function getCarrierName($carrierCode)
	{
		if (($name = trim(Mage::getStoreConfig('carriers/' . $carrierCode . '/title'))) !== '') {
			return $name;
		}

		return $carrierCode;
	}
	
	/**
	 * Determine whether or not to display the country selector
	 *
	 * @return bool
	 */
	public function canDisplayCountrySelector()
	{
		return substr_count($this->getCountryHtmlSelect(), '<option') > 2;
	}
	
	/**
	 * Retrieve the HTML for the country selector
	 *
	 * @return string
	 */
	public function getCountryHtmlSelect()
	{
		if (!$this->hasCountryHtmlSelect()) {
			$this->setCountryHtmlSelect(
				$this->_getDirectoryDataBlock()->getCountryHtmlSelect($this->helper('basketshipping')->getCountryId(), 'country_id', 'shipping-country')
			);
		}
		
		return $this->_getData('country_html_select');
	}
	
	/**
	 * Retrieve the directory data block
	 * This is usd to generate the country html select
	 *
	 * @return Mage_Directory_Block_Data
	 */
	protected function _getDirectoryDataBlock()
	{
		if (is_null($this->_directoryDataBlock)) {
			$this->_directoryDataBlock = $this->getLayout()->createBlock('directory/data');
		}
		
		return $this->_directoryDataBlock;
	}
	
	/**
	 * Get the current region id
	 *
	 * @return string
	 */
	public function getEstimateRegionId()
	{
		return $this->getShippingBlock()->getEstimateRegionId();
	}

	/**
	 * Get the current postcode
	 *
	 * @return string
	 */	
	public function getPostcode()
	{
		return $this->getShippingBlock()->getEstimatePostcode();
	}

	/**
	 * Determine whether to show the region field
	 *
	 * @return bool
	 */
	public function canShowRegion()
	{
		return Mage::getStoreConfig('basketshipping/settings/show_region');
	}
}
