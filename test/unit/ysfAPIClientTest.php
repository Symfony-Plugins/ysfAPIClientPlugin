<?php

/**
 *
 * Copyright (c) 2008 Yahoo! Inc.  All rights reserved.
 * The copyrights embodied in the content in this file are licensed
 * under the MIT open source license.
 *
 * For the full copyright and license information, please view the LICENSE.yahoo
 * file that was distributed with this source code.
 */

$sf_symfony_lib_dir = '/Users/dustin/projects/symfony/branch/1.1/lib';

$sf_root_dir = realpath(dirname(__FILE__).'/../../fixtures/project');

require_once($sf_root_dir.'/config/ProjectConfiguration.class.php');
$configuration = new ProjectConfiguration($sf_root_dir);

// load lime
require_once($configuration->getSymfonyLibDir().'/vendor/lime/lime.php');

$t = new lime_test(1, new lime_output_color());

$t->diag('->getInstance()');
$t->is(ysfAPIClient::getInstance(), '', 'ysfAPIClient can get an active instance.');
