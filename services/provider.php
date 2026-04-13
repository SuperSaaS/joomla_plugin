<?php
/**
 * @package     SuperSaaS.Plugin
 * @subpackage  Content.supersaas
 *
 * @copyright   Copyright (C) 2026 SuperSaaS, Inc. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;
use SuperSaaS\Plugin\Content\Supersaas\Extension\Supersaas;

return new class implements ServiceProviderInterface {
    public function register(Container $container): void
    {
        $container->set(
            PluginInterface::class,
            function (Container $container) {
                $plugin = new Supersaas(
                    $container->get(DispatcherInterface::class),
                    (array) PluginHelper::getPlugin('content', 'supersaas')
                );
                $plugin->setApplication(Factory::getApplication());

                return $plugin;
            }
        );
    }
};
