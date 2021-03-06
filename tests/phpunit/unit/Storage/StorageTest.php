<?php

namespace Bolt\Tests\Storage;

use Bolt\Legacy;
use Bolt\Events\StorageEvents;
use Bolt\Legacy\Content;
use Bolt\Legacy\Storage;
use Bolt\Tests\BoltUnitTest;
use Bolt\Tests\Mocks\LoripsumMock;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class to test src/Storage.
 *
 * @author Ross Riley <riley.ross@gmail.com>
 */
class StorageTest extends BoltUnitTest
{
    /**
     * We copy the 'Pages' ContentType into a 'Fakes' configuration and test
     * against this, as PHPUnit keeps the mock in the $this->mockObjects private
     * property array and any further calls to the 'Pages' repo will (currently)
     * fail as the Application object is torn down at the end of the test/class.
     *
     * @expectedException \Exception
     * @expectedExceptionMessage Failing does not extend \Bolt\Legacy\Content.
     */
    public function testGetContentObject()
    {
        $app = $this->getApp();
        $storage = new Storage($app);
        $content = $storage->getContentObject('pages');
        $this->assertInstanceOf(Legacy\Content::class, $content);

        // Fake it until we make it… to the end of the test suite.
        $contentType = $app['config']->get('contenttypes/pages');
        $contentType['name'] = 'Fakes';
        $contentType['singular_name'] = 'Fake';
        $contentType['slug'] = 'fakes';
        $contentType['singular_slug'] = 'fake';
        $contentType['tablename'] = 'fakes';
        $app['config']->set('contenttypes/fakes', $contentType);

        $fields = $app['config']->get('contenttypes/fakes/fields');

        $mock = $this->getMockBuilder(Content::class)
            ->setMethods([])
            ->setConstructorArgs([$app])
            ->setMockClassName('Fakes')
            ->getMock()
        ;
        $content = $storage->getContentObject(['class' => 'Fakes', 'fields' => $fields]);
        $this->assertInstanceOf('Fakes', $content);
        $this->assertInstanceOf(Legacy\Content::class, $content);

        // Test that a class not instanceof Bolt\Legacy\Content fails
        $mock = $this->getMockBuilder(\stdClass::class)
            ->setMethods(null)
            ->setConstructorArgs([])
            ->setMockClassName('Failing')
            ->getMock()
        ;
        $storage->getContentObject(['class' => 'Failing', 'fields' => $fields]);
    }

    public function testPreFill()
    {
        $this->resetDb();
        $app = $this->getApp();
        $this->addDefaultUser($app);
        $prefillMock = new LoripsumMock();
        $this->setService('prefill', $prefillMock);

        $storage = new Storage($app);
        $output = $storage->prefill(['showcases']);
        $this->assertRegExp('#Added#', $output);

        $output = $storage->prefill();
        $this->assertRegExp('#Skipped#', $output);
    }

    /**
     * @expectedException \Bolt\Exception\StorageException
     * @expectedExceptionMessage Contenttype is required for saveContent
     */
    public function testSaveContentMissingContentType()
    {
        $app = $this->getApp();
        $app['request'] = Request::create('/');
        $storage = new Storage($app);

        // Test missing contenttype handled
        $content = new Content($app);
        $storage->saveContent($content);
    }

    public function testSaveContentPrePostDispatchers()
    {
        $app = $this->getApp();
        $app['request'] = Request::create('/');
        $storage = new Storage($app);

        // Test dispatcher is called pre-save and post-save
        $content = $storage->getEmptyContent('showcases');

        $presave = 0;
        $postsave = 0;
        $listener = function () use (&$presave) {
            ++$presave;
        };
        $listener2 = function () use (&$postsave) {
            ++$postsave;
        };
        $app['dispatcher']->addListener(StorageEvents::PRE_SAVE, $listener);
        $app['dispatcher']->addListener(StorageEvents::POST_SAVE, $listener2);
        $storage->saveContent($content);
        $this->assertEquals(1, $presave);
        $this->assertEquals(1, $postsave);
    }

    public function testSaveContent()
    {
        $app = $this->getApp();
        $app['request'] = Request::create('/');
        $storage = new Storage($app);

        $content = $storage->getEmptyContent('showcases');
        $content->setValues([
            'title'  => 'koala',
            'slug'   => 'Kenny',
            'status' => 'published',
        ]);

        $result = $storage->saveContent($content);
        $stored = $storage->getContent('showcase/' . $result);

        $this->assertInstanceOf(Content::class, $stored);
    }

    /**
     * @expectedException \Bolt\Exception\StorageException
     * @expectedExceptionMessage Contenttype is required for deleteContent
     */
    public function testDeleteContentBadParameters()
    {
        $app = $this->getApp();
        $app['request'] = Request::create('/');
        $storage = new Storage($app);

        // Test delete fails on missing params
        $this->assertFalse($storage->deleteContent('', 999));
    }

    public function testDeleteContent()
    {
        $app = $this->getApp();
        $app['request'] = Request::create('/');
        $storage = new Storage($app);

        // Test the delete events are triggered
        $delete = 0;
        $listener = function () use (&$delete) {
            ++$delete;
        };
        $app['dispatcher']->addListener(StorageEvents::PRE_DELETE, $listener);
        $app['dispatcher']->addListener(StorageEvents::POST_DELETE, $listener);

        $storage->deleteContent(['slug' => 'showcases'], 1);

        $this->assertFalse($storage->getContent('showcases/1'));
        $this->assertEquals(2, $delete);
    }

    public function testUpdateSingleValue()
    {
        $app = $this->getApp();
        $app['request'] = Request::create('/');
        $storage = new Storage($app);

        $fetch1 = $storage->getContent('showcases/2');
        $this->assertEquals(1, $fetch1->get('ownerid'));
        $result = $storage->updateSingleValue('showcases', 2, 'ownerid', '10');
        $this->assertEquals(2, $result);

        $fetch2 = $storage->getContent('showcases/2');
        $this->assertEquals('10', $fetch2->get('ownerid'));

        // Test invalid column fails
        $shouldError = $storage->updateSingleValue('showcases', 2, 'nonexistent', '10');
        $this->assertFalse($shouldError);
    }

    public function testGetEmptyContent()
    {
        $app = $this->getApp();
        $storage = new Storage($app);
        $showcase = $storage->getEmptyContent('showcase');
        $this->assertInstanceOf(Legacy\Content::class, $showcase);
        $this->assertEquals('showcases', $showcase->contenttype['slug']);
    }

    public function testSearchContent()
    {
        $app = $this->getApp();
        $app['request'] = Request::create('/');
        $storage = new Storage($app);

        // Test simple search
        $result = $storage->searchContent('lorem');
        $this->assertGreaterThan(0, count($result));
        $this->assertTrue($result['query']['valid']);

        // Test complex search
        $result = $storage->searchContent('I like lorem');
        $this->assertGreaterThan(0, count($result));
        $this->assertTrue($result['query']['valid']);

        // Test invalid query fails
        $result = $storage->searchContent('x');
        $this->assertFalse($result);

        // Test filters
        $result = $storage->searchContent('lorem', ['showcases'], ['showcases' => ['title' => 'nonexistent']]);
        $this->assertTrue($result['query']['valid']);
        $this->assertEquals(0, $result['no_of_results']);
    }

    public function testSearchAllContentTypes()
    {
        $app = $this->getApp();
        $app['request'] = Request::create('/');
        $storage = new Storage($app);
        $results = $storage->searchAllContentTypes(['title' => 'lorem']);
    }

    public function testGetContentSortOrderFromContentType()
    {
        $app = $this->getApp();
        $app['request'] = Request::create('/');
        $db = $this->getDbMockBuilder($app['db'])
            ->setMethods(['fetchAll'])
            ->getMock();
        $this->setService('db', $db);
        $db->expects($this->any())
            ->method('fetchAll')
            ->willReturn([]);
        $storage = new Mock\StorageMock($app);

        // Test sorting is pulled from contenttype when not specified
        $app['config']->set('contenttypes/entries/sort', '-id');
        $storage->getContent('entries');
        $this->assertSame('ORDER BY "id" DESC', $storage->queries[0]['queries'][0]['order']);
    }

    public function testGetContentReturnSingleLimits1()
    {
        $app = $this->getApp();
        $app['request'] = Request::create('/');
        $db = $this->getDbMockBuilder($app['db'])
            ->setMethods(['fetchAll'])
            ->getMock();
        $this->setService('db', $db);
        $db->expects($this->any())
            ->method('fetchAll')
            ->willReturn([]);
        $storage = new Mock\StorageMock($app);

        // Test returnsingle will set limit to 1
        $storage->getContent('entries', ['returnsingle' => true]);
        $this->assertSame(1, $storage->queries[0]['parameters']['limit']);
    }

    /**
     * The legacy getContentType method should be able to find contenttypes by key, slugified key, slug, slugified slug,
     * singular slug, slugified singular slug, singular name and name.
     *
     * @dataProvider contentTypeProvider
     *
     * @param array $contentType
     */
    public function testGetContentType($contentType)
    {
        /** @var \Bolt\Application */
        $app = $this->getApp();

        $app['config']->set('contenttypes/' . $contentType['key'], $contentType);

        // We should be able to retrieve $contentType when querying getContentType() for its key, slug, singular
        // slug, name and singular name
        foreach ($contentType as $key => $value) {
            $this->assertSame($contentType, $app['storage']->getContentType($value));
        }
    }

    /**
     * Seed some dummy content types for testing the ContentType query methods.
     */
    public function contentTypeProvider()
    {
        return [
            [
                [
                    'key'           => 'foo_bars',
                    'slug'          => 'foo_bars',
                    'singular_slug' => 'foo_bar',
                    'name'          => 'FooBars',
                    'singular_name' => 'Foo Bar',
                ],
            ],
            [
                [
                    'key'           => 'somethingelse',
                    'slug'          => 'things',
                    'singular_slug' => 'thing',
                    'name'          => 'Somethings',
                    'singular_name' => 'Something',
                ],
            ],
        ];
    }

    private function getDbMockBuilder(Connection $db)
    {
        return $this->getMockBuilder('\Doctrine\DBAL\Connection')
            ->setConstructorArgs([$db->getParams(), $db->getDriver(), $db->getConfiguration(), $db->getEventManager()])
            ->enableOriginalConstructor()
        ;
    }
}
