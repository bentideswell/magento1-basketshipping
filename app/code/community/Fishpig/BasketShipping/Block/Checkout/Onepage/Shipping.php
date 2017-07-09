<?php
/**
 * @category		Fishpig
 * @package		Fishpig_BasketShipping
 * @license		http://fishpig.co.uk/license.txt
 * @author		Ben Tideswell <help@fishpig.co.uk>
 */

class Fishpig_BasketShipping_Block_Checkout_Onepage_Shipping extends Mage_Checkout_Block_Onepage_Shipping
{
	/**
	 * Retrieve the address and if necessary, set the country ID
	 *
	 * @return Mage_Sales_Model_Quote_Address
	 */
	public function getAddress()
	{
		if ($address = parent::getAddress()) {
			if (!$address->getId() && ($countryId = $this->helper('basketshipping')->getCountryId())) {
				$address->setCountryId($countryId);
			}	
		}
		
		return $address;
	}
}