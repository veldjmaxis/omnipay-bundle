<?php

/*
 * This file is part of the colinodell\omnipay-bundle package.
 *
 * (c) 2018 Colin O'Dell <colinodell@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ColinODell\OmnipayBundle\Tests\DependencyInjection;

use ColinODell\OmnipayBundle\DependencyInjection\OmnipayExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

abstract class OmnipayExtensionTest extends \PHPUnit_Framework_TestCase
{
    abstract protected function loadFromFile(ContainerBuilder $container, $file);

    public function testDefaultOmnipayService()
    {
        $container = $this->createContainerFromFile('default');

        $this->assertTrue($container->hasDefinition('omnipay'));

        $definition = $container->getDefinition('omnipay');

        $this->assertEquals('ColinODell\OmnipayBundle\Service\Omnipay', $definition->getClass());
    }

    public function testConfiguredOmnipayService()
    {
        $container = $this->createContainerFromFile('methods');

        $this->assertValidContainer($container);
    }

    public function testConfiguredOmnipayWithDefaultService()
    {
        $container = $this->createContainerFromFile('methods-with-default-gateway');

        $this->assertValidContainer($container, 'Stripe');
    }

    public function testConfiguredOmnipayWithDisabledGateways()
    {
        $container = $this->createContainerFromFile('methods-with-disabled-gateways');

        $this->assertValidContainer($container, null, ['Stripe']);
    }

    public function testConfiguredOmnipayWithDefaultGatewayAdnDisabledGateways()
    {
        $container = $this->createContainerFromFile('methods-with-default-gateway-and-disabled-gateways');

        $this->assertValidContainer($container, 'Stripe', ['PayPal_Express']);
    }

    public function testConfiguredOmnipayServiceWithInitializeOnRegistration()
    {
        $container = $this->createContainerFromFile('methods-with-initialize-on-registration');

        $this->assertValidContainer($container);
    }

    /**
     * @expectedException \Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     */
    public function testOmnipayServiceWithNonExistingDefaultGateway()
    {
        $this->createContainerFromFile('non-existing-default-gateway');
    }

    /**
     * @expectedException \Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     */
    public function testOmnipayServiceWithDisabledDefaultGateway()
    {
        $this->createContainerFromFile('disabled-default-gateway');
    }

    protected static function getSampleMethodConfig()
    {
        return [
            'Stripe' => [
                'apiKey' => 'sk_test_BQokikJOvBiI2HlWgH4olfQ2',
            ],
            'PayPal_Express' => [
                'username' => 'test-facilitator_api1.example.com',
                'password' => '3MPI3VB4NVQ3XSVF',
                'signature' => '6fB0XmM3ODhbVdfev2hUXL2x7QWxXlb1dERTKhtWaABmpiCK1wtfcWd.',
                'testMode' => false,
                'solutionType' => 'Sole',
                'landingPage' => 'Login',
            ],
        ];
    }

    /**
     * @return ContainerBuilder
     */
    protected function createContainer()
    {
        $bundles = [
            'OmnipayBundle' => 'ColinODell\OmnipayBundle\OmnipayBundle',
        ];

        $container = new ContainerBuilder(new ParameterBag([
            'kernel.bundles'     => $bundles,
            'kernel.cache_dir'   => sys_get_temp_dir(),
            'kernel.debug'       => false,
            'kernel.environment' => 'test',
            'kernel.name'        => 'kernel',
            'kernel.root_dir'    => __DIR__,
        ]));

        return $container;
    }

    /**
     * @param string $file
     *
     * @return ContainerBuilder
     */
    protected function createContainerFromFile($file)
    {
        if (getenv('TRAVIS') !== false && PHP_MAJOR_VERSION == 5 && in_array(PHP_MINOR_VERSION, [5, 6])) {
            $this->markTestSkipped('This test fails on Travis CI for some unknown, but passes in other environments using these same versions');
        }

        $container = $this->createContainer();

        $container->registerExtension(new OmnipayExtension());
        $this->loadFromFile($container, $file);

        $container->compile();

        return $container;
    }

    /**
     * @param ContainerBuilder $container
     * @param string|null $defaultGateway
     * @param array $disabledGateways
     * @param null $initializeOnRegistration
     */
    private function assertValidContainer(
        ContainerBuilder $container,
        $defaultGateway = null,
        $disabledGateways = [],
        $initializeOnRegistration = null
    ) {
        $this->assertTrue($container->hasDefinition('omnipay'));

        $definition = $container->getDefinition('omnipay');

        $this->assertEquals('ColinODell\OmnipayBundle\Service\Omnipay', $definition->getClass());
        $this->assertEquals('Omnipay\Common\GatewayFactory', $definition->getArgument(0)->getClass());
        $this->assertEquals(self::getSampleMethodConfig(), $definition->getArgument(1));

        if ($defaultGateway) {
            $this->assertEquals([$defaultGateway], $this->getMethodCallArguments($definition, 'setDefaultGatewayName'));
        }

        if ($disabledGateways) {
            $this->assertEquals([$disabledGateways], $this->getMethodCallArguments($definition, 'setDisabledGateways'));
        }

        if ($initializeOnRegistration) {
            $this->assertEquals(
                $initializeOnRegistration,
                $this->getMethodCallArguments($definition, 'initializeOnRegistration')
            );
        }
    }

    /**
     * @param Definition $definition
     * @param string $method
     * @return mixed
     */
    private function getMethodCallArguments(Definition $definition, $method)
    {
        foreach ($definition->getMethodCalls() as $methodCall) {
            list($methodName, $arguments) = $methodCall;

            if ($methodName === $method) {
                return $arguments;
            }
        }

        $this->assertTrue(false, sprintf('Method call %s has not been added to the definition', $method));
    }
}
