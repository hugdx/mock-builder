<?php


namespace HungDX\MockBuilder\Integration;

use HungDX\MockBuilder\MockBuilder;
use Mockery\Generator\MockConfigurationBuilder;

$className = '\HungDX\MockBuilder\Integration\AuthenticateMockable';

// Class defined
if (class_exists($className)) {
    return;
}

// Not running with phpunit
if (!MockBuilder::isTestingMode()) {
    return require_once __DIR__ . '/ModelMockable.d.php';
}

$config = new MockConfigurationBuilder();
$config->addTarget('\Illuminate\Foundation\Auth\User');
$config->setConstantsMap([
    $className => [
        'MOCK_CLASS_METHODS' => [
            '\Illuminate\Database\Eloquent\Builder',
            '\Illuminate\Database\Query\Builder',
        ],
    ],
]);

MockBuilder::createClass($className, $config);
