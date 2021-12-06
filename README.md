# SuperSaaS Online Appointment Scheduling -- Joomla! Plugin

The SuperSaaS Joomla! plugin displays a "Book now" button that automatically logs the user into a SuperSaaS schedule using his Joomla! user name. It passes the user's information along, creating or updating the user's information on SuperSaaS as needed.

Note that you will need to configure both the Joomla! plugin *and* your SuperSaaS account. Please read the setup instructions at:

<http://www.supersaas.com/info/doc/integration/joomla_integration>

___Warning: If you do not ask your users to log in to your own website, you should follow the general instructions on how to [integrate a schedule](http://www.supersaas.com/info/doc/integration "Integration | Integrate a schedule in your website") in your website. The plugin provided here will only work when the user is already logged into your own Joomla! site.___

Once installed you can add a button to your pages by placing a shortcode in the text of a Joomla! article:

* Default button example:
```
[supersaas]
```
* A custom button example:
```
[supersaas after=schedule_name label="Book Here!" image='http://cdn.supersaas.net/en/but/book_now_blue.png']
```

The shortcode takes the following optional arguments.

* `after` - The name of the schedule or an URL. Defaults to the schedule configured on the Joomla! Administrator page at the SuperSaaS Booking Plugin section. Entering a schedule name on the Joomla! Administrator page is optional.
* `label` - The button label. This defaults to “Book Now” or its equivalent in the supported languages. If the button has a background image, this will be the *alternate* text value.
* `image` - The URL of the background image. This has no default value. So, the button will not have a background image, if this isn’t configured.

The rendered button (v2.2 onwards) has a CSS class of "supersaas_login". The button style can be modified by customising this CSS class in your template.
### Technical details

The SuperSaaS Joomla! plugin listens to the *onContentPrepare* event triggered by the following built-in Joomla! contents: archive, article, category, featured article, tag and the *JHTMLContent* utility class used for non-article based content.
Of course, the plugin will listen to any custom Joomla! class that triggers the *onContentPrepare* event, but for the most common use cases this will not be necessary.
In the content passed each SuperSaaS Shortcode encountered will be replaced with a "Book Now" button.
