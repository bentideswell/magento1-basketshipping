/**
 * @category  Fishpig
 * @package  Fishpig_BasketShipping
 * @license  http://fishpig.co.uk/license.txt
 * @author  Ben Tideswell <help@fishpig.co.uk>
 */

var FishPig = FishPig || {}

FishPig.BasketShipping = {};

FishPig.BasketShipping.Regions = Class.create(RegionUpdater, {
    initialize: function ($super, app, countryEl, regionTextEl, regionSelectEl, regions, disableAction, zipEl) {
	    this.app = app;
		this.regionWrapper = $('bs:region');

	   $super(countryEl, regionTextEl, regionSelectEl, regions, disableAction, zipEl);
	   
	   this.update();
	   this.app.ready = true;
    },
	setMarkDisplay: function($super, elem, display) {
		$super(elem, display);

		if (!this.app) {
			return this;
		}

		if (!display) {
			this.regionWrapper.hide();
			this.app.submitAddressForm();
		}
		else {
			this.regionWrapper.show();
		}

		return this;
	}
});

FishPig.BasketShipping.App = Class.create({
	initialize: function(addressUrl) {
		this.addressUrl = addressUrl;
		this.method = $('shipping-method') || false;
		this.ready = false;

		if (this.method) {
			this._initSwitchMethod();
		}
		
		this._addressElements = new Array();

		['shipping-country', 'region_id', 'region', 'postcode', 'estimate_postcode'].each(function(type) {
			if ($(type)) {
				this._addressElements.push($(type));
			}
		}.bind(this));
		
		var countryId = this.getAddressElement('country_id');
		
		if (countryId) {
			countryId.observe('change', this.onCountryChangeObserver.bind(this));
		}
		
	   $$('.update-bs').invoke('observe', 'click', this.submitAddressFormObserver.bindAsEventListener(this));
	},
	getAddressElement: function(name) {
		var max = this._addressElements.length;

		for (var i = 0; i < max; i++) {
			if (this._addressElements[i].readAttribute('name') === name) {
				return this._addressElements[i];
			}
		}
	
		return false;
	},
	onCountryChangeObserver: function() {
		if (typeof this.regions !== 'object') {
			this.ready = true;

			return this.submitAddressForm();
		}
		
		return this;
	},
	initRegionUpdater: function(regionJson) {

		var region = this.getAddressElement('region');
		var regionId = this.getAddressElement('region_id');
		
		if (!regionId || !region) {
			return this;
		}
		
		this.regions = new FishPig.BasketShipping.Regions(this, 'shipping-country', 'region', 'region_id', regionJson);
		
		regionId.observe('change', this.submitAddressForm.bind(this));
	},
	submitAddressFormObserver: function(event) {
		Event.stop(event);
		
		this.submitAddressForm();
	},
	submitAddressForm: function() {
		if (this.ready) {
			var form = new Element('form', {method: 'post', action: this.addressUrl});

			this._addressElements.each(function(elem) {
				form.insert(new Element('input', {name: elem.readAttribute('name'), value: elem.getValue()}).setValue(elem.getValue()));
			}.bind(this));

			$(document.body).insert(form);
	
			form.submit();
		}

		return this;		
	},
	_initSwitchMethod: function() {
		var form = this.method.up('form');

		this.method.observe('change', form.submit.bindAsEventListener(form));
	}
});
