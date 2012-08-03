<?php

namespace Aws\Tests\Common\Client;

use Aws\Common\Client\ClientBuilder;
use Aws\DynamoDb\DynamoDbClient;
use Aws\Common\Signature\SignatureV4;
use Aws\Common\Client\ExponentialBackoffOptionResolver;
use Aws\Common\Exception\Parser\DefaultJsonExceptionParser;
use Aws\Common\Client\CredentialsOptionResolver;
use Aws\Common\Client\SignatureOptionResolver;
use Aws\Common\Credentials\Credentials;
use Guzzle\Common\Collection;
use Guzzle\Http\Plugin\ExponentialBackoffPlugin;

/**
 * Note: The tests for the build method do not mock anything
 *
 * @covers Aws\Common\Client\ClientBuilder
 */
class ClientBuilderTest extends \Guzzle\Tests\GuzzleTestCase
{
    public function testBuild()
    {
        $client = ClientBuilder::factory('Aws\\DynamoDb')
            ->setConfig(array())
            ->setConfigDefaults(array(
                'scheme'   => 'https',
                'region'   => 'us-east-1',
                'base_url' => '{scheme}://dynamodb.{region}.amazonaws.com'
            ))
            ->setConfigRequirements(array('base_url'))
            ->setSignature(new SignatureV4())
            ->setExceptionParser(new DefaultJsonExceptionParser())
            ->build();

        $this->assertInstanceOf('Aws\DynamoDb\DynamoDbClient', $client);
    }

    public function testBuildAlternate()
    {
        $client = ClientBuilder::factory('Aws\\DynamoDb')
            ->setConfigDefaults(array(
                'base_url' => '{scheme}://dynamodb.{region}.amazonaws.com'
            ))
            ->setCredentialsResolver(new CredentialsOptionResolver(function (Collection $config) {
                return Credentials::factory($config->getAll(array_keys(Credentials::getConfigDefaults())));
            }))
            ->setSignatureResolver(new SignatureOptionResolver(function () {
                return new SignatureV4();
            }))
            ->addClientResolver(new ExponentialBackoffOptionResolver(function() {
                return new ExponentialBackoffPlugin();
            }))
            ->build();

        $this->assertInstanceOf('Aws\DynamoDb\DynamoDbClient', $client);
    }

    /**
     * @expectedException Aws\Common\Exception\InvalidArgumentException
     */
    public function testBuildThrowsExceptionWithoutSignature()
    {
        $client = ClientBuilder::factory('Aws\\DynamoDb')
            ->setConfigDefaults(array(
                'base_url' => '{scheme}://dynamodb.{region}.amazonaws.com'
            ))
            ->build();

        $this->assertInstanceOf('Aws\DynamoDb\DynamoDbClient', $client);
    }

    public function testResolvesSslOptions()
    {
        $builder = $this->getMockBuilder('Aws\Common\Client\ClientBuilder')
            ->disableOriginalConstructor()
            ->getMock();

        // make the method callable
        $method = new \ReflectionMethod('Aws\Common\Client\ClientBuilder', 'resolveSslOptions');
        $method->setAccessible(true);

        $config = new Collection();

        // Ensure that the default setting of 'true' uses the Mozilla certs
        $config->set('ssl.cert', true);
        $method->invoke($builder, $config);
        $this->assertContains('mozilla' . DIRECTORY_SEPARATOR . 'cacert' . DIRECTORY_SEPARATOR . 'cacert.pem', $config->get('curl.CURLOPT_CAINFO'));

        // Ensure that a custom setting uses the custom setting
        $config->set('ssl.cert', '/foo/bar');
        $method->invoke($builder, $config);
        $this->assertEquals('/foo/bar', $config->get('curl.CURLOPT_CAINFO'));
    }

    public function getDataForProcessArrayTest()
    {
        return array(
            array(
                array('foo' => 'bar', 'bar' => 'baz'),
                array('foo' => 'bar', 'bar' => 'baz'),
            ),
            array(
                new Collection(array('foo' => 'bar', 'bar' => 'baz')),
                array('foo' => 'bar', 'bar' => 'baz'),
            ),
            array(
                'foo',
                null
            )
        );
    }

    /**
     * @dataProvider getDataForProcessArrayTest
     */
    public function testProcessArrayProcessesCorrectly($input, $expected)
    {
        $builder = ClientBuilder::factory('Aws\\DynamoDb');

        try {
            $builder->setConfig($input);
            $actual = $this->readAttribute($builder, 'config');
        } catch (\InvalidArgumentException $e) {
            $actual = null;
        }

        $this->assertEquals($expected, $actual);
    }
}