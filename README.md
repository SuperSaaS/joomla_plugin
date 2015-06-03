# SuperSaaS Online Appointment Scheduling -- Joomla Plugin

The SuperSaaS Joomla! plugin displays a "Book now" button that automatically logs the user into a SuperSaaS schedule using his Joomla user name. It passes the user's information along, creating or updating the user's information on SuperSaaS as needed.

Note that you will need to configure both the Joomla plugin *and* your SuperSaaS account. Please read the setup instructions here:

[www.supersaas.com/info/doc/integration/joomla_integration](http://www.supersaas.com/info/doc/integration/joomla_integration)

Once installed you can add a button to your pages by placing a shortcode in the text of a Joomla article:

* Default button example:
```
[supersaas]
```
* A custom button example:
```
[supersaas schedule=booking_system button_label="Book Here!" button_image_src='http://link.to/background.png']
```

The shortcode takes the following optional arguments.

* `after` - The name of the schedule or an URL. Defaults to the schedule configured on the Joomla! Administrator page at the SuperSaaS Booking Plugin section. Entering a schedule name on the Joomla! Administrator page is optional.
* `label` - The button label. This defaults to “Book Now” or its equivalent in the supported languages. If the button has a background image, this will be the 'alternate text value.
* `image` - The URL of the background image. This has no default value. So, the button will not have a background image, if this isn’t configured.

### Technical details

The SuperSaaS Joomla! plugin listens to the "onContentPrepare" event triggered by the following built-in Joomla contents: archive, article, category, featured article, tag and the JHTMLContent utility class used for non-article based content.
Of course, the plugin will listen to any custom Joomla! class that triggers the "onContentPrepare" event, but for the most common use cases this will not be necessary.
In the content passed to it the plugin replaces each SuperSaaS Shortcode encountered with a "Book Now" button.
