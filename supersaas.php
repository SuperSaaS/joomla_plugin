<?php
/**
 * @package     Supersaas.Plugin
 * @subpackage  Content.supersaas
 *
 * @copyright   Copyright (C) 2015 SuperSaaS, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @link        https://github.com/SuperSaaS/joomla_plugin
 * @link        http://www.supersaas.com/info/doc/integration/joomla_integration
 */

defined('_JEXEC') or die;

/**
 * SuperSaaS  Plugin
 *
 * @since  3.4
 */
class PlgContentSupersaas extends JPlugin
{
	/**
	 * The regular expression matching the supersaasbutton shortcode.
	 *
	 * @var	string
	 * @since  3.4
	 */
	const BUTTON_SHORTCODE_REGEX = '/\[supersaas(.*?)\]/iU';

	/**
	 * The regular expression matching the shortcode options and values.
	 *
	 * @var	integer
	 * @since  3.4
	 */
	const SHORTCODE_ATTR_REGEX = '/(\w+)\s*=\s*"([^"]*)"(?:\s|$)|(\w+)\s*=\s*\'([^\']*)\'(?:\s|$)|(\w+)\s*=\s*([^\s\'"]+)(?:\s|$)/';

	/**
	 * Affects constructor behavior. If true, language files will be loaded automatically.
	 *
	 * @var	boolean
	 * @since  3.4
	 */
	protected $autoloadLanguage = true;

	/** List of available shortcode attributes.
	 *
	 * @var boolean
	 * @since 3.4
	 */
	private static $shortcode_options = array('schedule', 'button_label', 'button_image_src');

	/**
	 * Plugin that adds SuperSaaS content to the content of an article.
	 *
	 * @param   string   $context   The context of the content being passed to the plugin.
	 * @param   object   &$article  The article object.  Note $article->text is also available
	 * @param   mixed    &$params   The article params
	 * @param   integer  $page      The 'page' number
	 *
	 * @return   mixed  Always returns void or true
	 *
	 * @since   3.4
	 */
	public function onContentPrepare($context, &$article, &$params, $page = 0)
	{
		// Don't run this plugin when the content is being indexed
		if ($context == 'com_finder.indexer')
		{
			return true;
		}

		$article->text = preg_replace_callback(self::BUTTON_SHORTCODE_REGEX, array($this, '_renderButton'), $article->text);
	}

	/**
	 * A callback that will be called and passed an array of matched elements.
	 *
	 * @param   array  $matches  An array of matched elements.
	 *
	 * @return   string  The replacement string.
	 */
	private function _renderButton($matches)
	{
		$user = JFactory::getUser();

		// Don't render the button if the user is not logged in.
		if ($user->guest)
		{
			return '';
		}

		$button_options = $matches[1];
		$settings = array_merge($this->_getPluginParams(), $this->_getShortcodeAttrs($matches[1]));

		// Don't render the button if the required things aren't set.
		if (!isset($settings['account_name']) || !isset($settings['password']) || !isset($settings['schedule']))
		{
			return JText::_('PLG_CONTENT_SS_SETUP_INCOMPLETE');
		}

		if (!isset($settings['button_label']))
		{
			$settings['button_label'] = JText::_('PLG_CONTENT_SS_BOOK_NOW');
		}

		if (!isset($settings['custom_domain'] ))
		{
			$api_endpoint = "http://" . JText::_('PLG_CONTENT_SS_CUSTOM_DOMAIN') . "/api/users";
		}
		elseif (filter_var($settings['custom_domain'], FILTER_VALIDATE_URL))
		{
			$api_endpoint = $settings['custom_domain'];
		}
		else
		{
			$api_endpoint = "http://" . rtrim($settings['custom_domain'], '/') . "/api/users";
		}

		$settings['custom_domain'] = rtrim($settings['custom_domain'], '/');
		$username = $user->get('username');
		$checksum = md5("{$settings['account_name']}{$settings['password']}{$username}");

		$content = "<form method=\"post\" action=\"{$api_endpoint}\">" .
		"<input type=\"hidden\" name=\"account\" value=\"{$settings['account_name']}\"/> " .
		"<input type=\"hidden\" name=\"id\" value=\"{$user->id}fk\"/>" .
		"<input type=\"hidden\" name=\"user[name]\" value=\"" . htmlspecialchars($username) . "\"/>" .
		"<input type=\"hidden\" name=\"user[full_name]\" value=\"" . htmlspecialchars($user->name) . "\"/>" .
		// Hack to display the email even if the emailcloack plugin is enabled.
		"<div style=\"display:none\">{emailcloak=off}</div>" .
		"<input type=\"hidden\" name=\"user[email]\" value=\"" . $user->email . "\"/> " .
		"<input type=\"hidden\" name=\"checksum\" value=\"{$checksum}\"/>" .
		"<input type=\"hidden\" name=\"after\" value=\"" . htmlspecialchars($settings['schedule']) . "\"/>";

		if (isset($settings['button_image_src']))
		{
			$content .= "<input type=\"image\" src=\"{$settings['button_image_src']}\" alt=\"" .
			htmlspecialchars($settings['button_label']) . "\" name=\"submit\" onclick=\"return confirmBooking()\"/>";
		}
		else
		{
			$content .= "<input type=\"submit\" value=\"" . htmlspecialchars($settings['button_label']) .
			"\" onclick=\"return confirmBooking()\"/>";
		}

		$content .= "</form><script type=\"text/javascript\">function confirmBooking() {" .
		"var reservedWords = ['administrator','supervise','supervisor','superuser','user','admin','supersaas'];" .
		"for (i = 0; i < reservedWords.length; i++) {if (reservedWords[i] === '{$username}') {return confirm('" .
		JText::_('PLG_CONTENT_SS_RESERVED_WORD') . "');}}}</script>";

		return $content;
	}

	/**
	 * Returns an array containing the shortcode options.
	 *
	 * @param   string  $settings_match  The shortcodes.
	 *
	 * @return  array  The shortcode options
	 *
	 * @since   3.4
	 */
	private function _getShortcodeAttrs($settings_match)
	{
		$attrs = array();

		if (preg_match_all(self::SHORTCODE_ATTR_REGEX, $settings_match, $attrs_match, PREG_SET_ORDER))
		{
			foreach ($attrs_match as $m)
			{
				if (!empty($m[1]))
				{
					self::_setShortcodeAttr($attrs, strtolower($m[1]), stripcslashes($m[2]));
				}
				elseif (!empty($m[3]))
				{
					self::_setShortcodeAttr($attrs, strtolower($m[3]), stripcslashes($m[4]));
				}
				elseif (!empty($m[5]))
				{
					self::_setShortcodeAttr($attrs, strtolower($m[5]), stripcslashes($m[6]));
				}
			}
		}

		return $attrs;
	}

	/**
	 * The first argument is the array of shortcodes. Sets the $attr key to the given $value.
	 *
	 * @param   array   &$attrs  The shortcodes.
	 * @param   string  $attr    The shortcodes.
	 * @param   string  $value   The shortcodes.
	 *
	 * @return  void
	 *
	 * @since   3.4
	 */
	private static function _setShortcodeAttr(&$attrs, $attr, $value)
	{
		if (in_array($attr, self::$shortcode_options))
		{
			$attrs[$attr] = $value;
		}
	}

	/**
	 * Returns an array containing the plugin params.
	 *
	 * @return  array  The plugin params.
	 *
	 * @since   3.4
	 */
	private function _getPluginParams()
	{
		return array(
			'account_name' => $this->params->get('account_name'),
			'password' => $this->params->get('password'),
			'custom_domain' => $this->params->get('custom_domain', JText::_('PLG_CONTENT_SS_DOMAIN')),
			'schedule' => $this->params->get('schedule'),
		);
	}
}
