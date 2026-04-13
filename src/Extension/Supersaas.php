<?php

namespace SuperSaaS\Plugin\Content\Supersaas\Extension;

\defined('_JEXEC') or die;

use Joomla\CMS\Event\Content\ContentPrepareEvent;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\SubscriberInterface;

/**
 * SuperSaaS Content Plugin
 *
 * Renders a 'Book now' button that automatically logs the Joomla user into
 * a SuperSaaS schedule using their Joomla credentials.
 *
 * @since  3.0.0
 */
class Supersaas extends CMSPlugin implements SubscriberInterface
{
    /**
     * Regular expression matching the [supersaas ...] shortcode.
     */
    private const BUTTON_SHORTCODE_REGEX = '/\[supersaas(.*?)\]/iU';

    /**
     * Regular expression matching key="value" pairs inside the shortcode.
     */
    private const SHORTCODE_ATTR_REGEX = '/(\w+)\s*=\s*"([^"]*)"(?:\s|$)|(\w+)\s*=\s*\'([^\']*)\'(?:\s|$)|(\w+)\s*=\s*([^\s\'"]+)(?:\s|$)/';

    /**
     * Allowed shortcode attribute names.
     */
    private static array $shortcodeOptions = ['after', 'label', 'image'];

    protected $autoloadLanguage = true;

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onContentPrepare' => 'onContentPrepare',
        ];
    }

    /**
     * Replaces [supersaas ...] shortcodes in article text with the booking form.
     *
     * @param   ContentPrepareEvent  $event  The content prepare event.
     *
     * @return  void
     *
     * @since   3.0.0
     */
    public function onContentPrepare(ContentPrepareEvent $event): void
    {
        // Do not run during search indexing.
        if ($event->getContext() === 'com_finder.indexer') {
            return;
        }

        $article = $event->getItem();

        if (!isset($article->text)) {
            return;
        }

        $article->text = preg_replace_callback(
            self::BUTTON_SHORTCODE_REGEX,
            [$this, 'renderButton'],
            $article->text
        );
    }

    /**
     * Builds the SuperSaaS SSO booking form HTML.
     *
     * @param   array  $matches  Regex matches from the shortcode pattern.
     *
     * @return  string  The rendered HTML or an empty string for guests.
     *
     * @since   3.0.0
     */
    private function renderButton(array $matches): string
    {
        $user = $this->getApplication()->getIdentity();

        // Do not render for guests.
        if ($user->guest) {
            return '';
        }

        $settings = array_merge($this->getPluginParams(), $this->getShortcodeAttrs($matches[1]));

        // Bail out when required settings are missing.
        if (!isset($settings['account_name']) || !isset($settings['password']) || !isset($settings['after'])) {
            return Text::_('PLG_CONTENT_SS_SETUP_INCOMPLETE');
        }

        if (!isset($settings['label'])) {
            $settings['label'] = Text::_('PLG_CONTENT_SS_BOOK_NOW');
        }

        $apiEndpoint = $this->buildApiEndpoint($settings);
        $settings['custom_domain'] = rtrim($settings['custom_domain'] ?? '', '/');

        $username = $user->get('username');
        $checksum = md5("{$settings['account_name']}{$settings['password']}{$username}");

        $html  = '<form method="post" action="' . $apiEndpoint . '">';
        $html .= '<input type="hidden" name="account" value="' . htmlspecialchars($settings['account_name'], ENT_QUOTES, 'UTF-8') . '"/> ';
        $html .= '<input type="hidden" name="id" value="' . (int) $user->id . 'fk"/>';
        $html .= '<input type="hidden" name="user[name]" value="' . htmlspecialchars($username, ENT_QUOTES, 'UTF-8') . '"/>';
        $html .= '<input type="hidden" name="user[full_name]" value="' . htmlspecialchars($user->name, ENT_QUOTES, 'UTF-8') . '"/>';
        $html .= '<div style="display:none">{emailcloak=off}</div>';
        $html .= '<input type="hidden" name="user[email]" value="' . htmlspecialchars($user->email, ENT_QUOTES, 'UTF-8') . '"/> ';
        $html .= '<input type="hidden" name="checksum" value="' . htmlspecialchars($checksum, ENT_QUOTES, 'UTF-8') . '"/>';
        $html .= '<input type="hidden" name="after" value="' . htmlspecialchars($settings['after'], ENT_QUOTES, 'UTF-8') . '"/>';

        if (isset($settings['image'])) {
            $html .= '<input type="image" src="' . htmlspecialchars($settings['image'], ENT_QUOTES, 'UTF-8') . '" alt="'
                . htmlspecialchars($settings['label'], ENT_QUOTES, 'UTF-8')
                . '" name="submit" onclick="return confirmBooking()" class="supersaas_login"/>';
        } else {
            $html .= '<input type="submit" value="' . htmlspecialchars($settings['label'], ENT_QUOTES, 'UTF-8')
                . '" onclick="return confirmBooking()" class="supersaas_login"/>';
        }

        $reservedWordWarning = Text::_('PLG_CONTENT_SS_RESERVED_WORD');
        $escapedUsername     = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
        $html .= '</form>';
        $html .= '<script type="text/javascript">';
        $html .= 'function confirmBooking() {';
        $html .= 'var reservedWords = ["administrator","supervise","supervisor","superuser","user","admin","supersaas"];';
        $html .= 'for (var i = 0; i < reservedWords.length; i++) {';
        $html .= 'if (reservedWords[i] === "' . $escapedUsername . '") {';
        $html .= 'return confirm("' . $reservedWordWarning . '");';
        $html .= '}}}';
        $html .= '</script>';

        return $html;
    }

    /**
     * Builds the SuperSaaS API endpoint URL from plugin settings.
     *
     * @param   array  $settings  Merged plugin + shortcode settings.
     *
     * @return  string
     *
     * @since   3.0.0
     */
    private function buildApiEndpoint(array $settings): string
    {
        if (empty($settings['custom_domain'])) {
            return 'https://' . Text::_('PLG_CONTENT_SS_DOMAIN') . '/api/users';
        }

        if (filter_var($settings['custom_domain'], FILTER_VALIDATE_URL)) {
            return $settings['custom_domain'];
        }

        return 'https://' . rtrim($settings['custom_domain'], '/') . '/api/users';
    }

    /**
     * Parses shortcode attribute key/value pairs.
     *
     * @param   string  $attrString  The raw attribute string from the shortcode.
     *
     * @return  array
     *
     * @since   3.0.0
     */
    private function getShortcodeAttrs(string $attrString): array
    {
        $attrs = [];

        if (!preg_match_all(self::SHORTCODE_ATTR_REGEX, $attrString, $matches, PREG_SET_ORDER)) {
            return $attrs;
        }

        foreach ($matches as $m) {
            if (!empty($m[1])) {
                self::setShortcodeAttr($attrs, strtolower($m[1]), stripcslashes($m[2]));
            } elseif (!empty($m[3])) {
                self::setShortcodeAttr($attrs, strtolower($m[3]), stripcslashes($m[4]));
            } elseif (!empty($m[5])) {
                self::setShortcodeAttr($attrs, strtolower($m[5]), stripcslashes($m[6]));
            }
        }

        return $attrs;
    }

    /**
     * Sets an attribute only if it is in the allowed list.
     *
     * @param   array   &$attrs  Target attributes array.
     * @param   string  $attr    Attribute name.
     * @param   string  $value   Attribute value.
     *
     * @return  void
     *
     * @since   3.0.0
     */
    private static function setShortcodeAttr(array &$attrs, string $attr, string $value): void
    {
        if (\in_array($attr, self::$shortcodeOptions, true)) {
            $attrs[$attr] = $value;
        }
    }

    /**
     * Returns plugin parameters as a settings array.
     *
     * @return  array
     *
     * @since   3.0.0
     */
    private function getPluginParams(): array
    {
        return [
            'account_name'  => $this->params->get('account_name'),
            'password'      => $this->params->get('password'),
            'custom_domain' => $this->cleanCustomDomain(),
            'after'         => $this->params->get('schedule'),
        ];
    }

    /**
     * Extracts only the host (+ optional port) from the custom_domain param.
     *
     * @return  string
     *
     * @since   3.0.0
     */
    private function cleanCustomDomain(): string
    {
        $customDomain = $this->params->get('custom_domain', Text::_('PLG_CONTENT_SS_DOMAIN'));
        $urlParts     = parse_url($customDomain);

        if (!isset($urlParts['host'])) {
            return $customDomain;
        }

        $domain = $urlParts['host'];

        if (isset($urlParts['port'])) {
            $domain .= ':' . $urlParts['port'];
        }

        return $domain;
    }
}
