<?php
namespace ESN\CalDAV;
require_once ESN_TEST_BASE . '/CalDAV/MockUtils.php';

class CalDAVPublisherPluginTest extends \PHPUnit_Framework_TestCase {

    const PATH = "calendars/123123/uid.ics";
    const ETAG = 'The etag';

    private function getPlugin($server = null) {
        $plugin = new CalDAVPublisherPluginMock($server);
        $server = $plugin->getServer();
        $this->data = "BEGIN:VCALENDAR\r\nBEGIN:VEVENT\r\nUID:a18225bc-3bfb-4e2a-a5f1-711c8d9cf531\r\nTRANSP:OPAQUE\r\nDTSTART;TZID=Europe/Berlin:20160209T113000\r\nDTEND;TZID=Europe/Berlin:20160209T140000\r\nSUMMARY:test\r\nORGANIZER;CN=admin admin:mailto:admin@open-paas.org\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";

        return $plugin;
    }

    private function mockTree($server) {
        $server->tree = $this->getMockBuilder('\Sabre\DAV\Tree')->disableOriginalConstructor()->getMock();
        $server->tree->expects($this->any())->method('nodeExists')
            ->with('/'.self::PATH)
            ->willReturn(true);

        $nodeMock = $this->getMockBuilder('\Sabre\DAV\File')->getMock();
        $nodeMock->expects($this->any())->method('getETag')->willReturn(self::ETAG);

        $server->tree->expects($this->any())->method('getNodeForPath')
            ->with('/'.self::PATH)
            ->will($this->returnValue($nodeMock));
    }

    function testCreateFile() {

        $modified = false;
        $calendarInfo = [
            'uri' => 'calendars/123123',
            'id' => '123123',
            'principaluri' => 'principals/communities/456456'
        ];
        $parent = new \Sabre\CalDAV\Calendar(new CalDAVBackendMock(), $calendarInfo);

        $plugin = $this->getPlugin();
        $server = $plugin->getServer();
        $this->mockTree($server);

        $client = $plugin->getClient();

        $this->assertTrue($server->emit('beforeCreateFile', [self::PATH, &$this->data, $parent, &$modified]));
        $this->assertTrue($server->emit('afterCreateFile', [self::PATH, $parent]));

        $jsondata = json_decode($client->message);
        $this->assertEquals($client->topic, 'calendar:event:updated');
        $this->assertEquals($jsondata->{'eventPath'}, "/" . self::PATH);
        $this->assertEquals($jsondata->{'etag'}, self::ETAG);
        $this->assertEquals($jsondata->{'type'}, 'created');
        $this->assertEquals($jsondata->{'event'}, json_decode(json_encode(\Sabre\VObject\Reader::read($this->data))));
        $this->assertEquals($jsondata->{'websocketEvent'}, 'calendar:ws:event:created');
    }

    function testCreateFileNonCalendarHome() {
        $plugin = $this->getPlugin();
        $server = $plugin->getServer();
        $client = $plugin->getClient();

        $modified = false;
        $parent = new \Sabre\DAV\SimpleCollection("root", []);

        $this->assertTrue($server->emit('beforeCreateFile', ["test", &$this->data, $parent, &$modified]));
        $this->assertTrue($server->emit('afterCreateFile', ["test", $parent]));
        $this->assertNull($client->message);
    }

    function testWriteContent() {
        $plugin = $this->getPlugin();
        $client = $plugin->getClient();
        $server = $plugin->getServer();
        $this->mockTree($server);

        $modified = false;
        $data = "BEGIN:VCALENDAR";

        $objectData = [
            'uri' => 'objecturi',
            'calendardata' => 'olddata'
        ];
        $calendarData = [
            'id' => '123123123',
            'principaluri' => 'principals/communities/456456456'
        ];
        $node = new \Sabre\CalDAV\CalendarObject(new CalDAVBackendMock(), $calendarData, $objectData);

        $this->assertTrue($server->emit('beforeWriteContent', [self::PATH, $node, &$this->data, &$modified]));
        $this->assertTrue($server->emit('afterWriteContent', [self::PATH, $node]));

        $jsondata = json_decode($client->message);
        $this->assertEquals($client->topic, 'calendar:event:updated');
        $this->assertEquals($jsondata->{'eventPath'}, "/" . self::PATH);
        $this->assertEquals($jsondata->{'type'}, 'updated');
        $this->assertEquals($jsondata->{'old_event'}, json_decode(json_encode($node)));
        $this->assertEquals($jsondata->{'event'}, json_decode(json_encode(\Sabre\VObject\Reader::read($this->data))));
        $this->assertEquals($jsondata->{'websocketEvent'}, 'calendar:ws:event:updated');
        $this->assertEquals($jsondata->{'etag'}, self::ETAG);
    }

    function testWriteContentNonACL() {
        $plugin = $this->getPlugin();
        $server = $plugin->getServer();
        $client = $plugin->getClient();

        $modified = false;
        $data = "BEGIN:VCALENDAR";
        $node = new \Sabre\DAV\SimpleFile("filename", "contents");

        $this->assertTrue($server->emit('beforeWriteContent', [self::PATH, $node, &$data, &$modified]));
        $this->assertTrue($server->emit('afterWriteContent', [self::PATH, $node]));
        $this->assertNull($client->message);
    }

    function testUnbind() {
        $data = "BEGIN:VCALENDAR";
        $objectData = [
            'uri' => 'uid.ics',
            'calendardata' => &$this->data
        ];
        $calendarData = [
            'id' => '123123',
            'principaluri' => 'principals/communities/456456456'
        ];
        $calendarObject = new \Sabre\CalDAV\CalendarObject(new CalDAVBackendMock(), $calendarData, $objectData);

        $parent = new \Sabre\DAV\SimpleFile("filename", "contents");
        $server = new \Sabre\DAV\Server([
            new \Sabre\DAV\SimpleCollection("calendars", [
                new \Sabre\DAV\SimpleCollection("123123", [
                    $calendarObject
                ])
            ])
        ]);

        $plugin = $this->getPlugin($server);
        $client = $plugin->getClient();

        $this->assertTrue($server->emit('beforeUnbind', [self::PATH]));
        $this->assertTrue($server->emit('afterUnbind', [self::PATH]));

        $jsondata = json_decode($client->message);
        $this->assertEquals($client->topic, 'calendar:event:updated');
        $this->assertEquals($jsondata->{'eventPath'}, "/" . self::PATH);
        $this->assertEquals($jsondata->{'type'}, 'deleted');
        $this->assertEquals($jsondata->{'event'}, json_decode(json_encode(\Sabre\VObject\Reader::read($this->data))));
        $this->assertEquals($jsondata->{'websocketEvent'}, 'calendar:ws:event:deleted');
    }

    function testUnbindNonCalendarObject() {
        $data = "BEGIN:VCALENDAR";

        $parent = new \Sabre\DAV\SimpleFile("filename", "contents");
        $server = new \Sabre\DAV\Server([
            new \Sabre\DAV\SimpleCollection("calendars", [
                new \Sabre\DAV\SimpleCollection("123123", [
                    new \Sabre\DAV\SimpleFile("uid.ics", "content")
                ])
            ])
        ]);

        $plugin = $this->getPlugin($server);
        $client = $plugin->getClient();
        $this->assertTrue($server->emit('beforeUnbind', [self::PATH]));
        $this->assertTrue($server->emit('afterUnbind', [self::PATH]));
        $this->assertNull($client->message);
    }
}

class PublisherMock implements \ESN\Utils\Publisher {
    public $topic;
    public $message;

    function publish($topic, $message) {
        $this->topic = $topic;
        $this->message = $message;
    }
}

class CalDAVPublisherPluginMock extends CalDAVPublisherPlugin {

    function __construct($server) {
        if (!$server) $server = new \Sabre\DAV\Server([]);
        $this->initialize($server);
        $this->client = new PublisherMock();
        $this->server = $server;
    }

    function getClient() {
        return $this->client;
    }

    function getMessage() {
        return $this->message;
    }

    function getServer() {
        return $this->server;
    }
}
