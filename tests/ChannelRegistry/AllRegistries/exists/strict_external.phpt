--TEST--
\PEAR2\Pyrus\ChannelRegistry::exists() strict external channel check
--FILE--
<?php
require dirname(__DIR__) . '/../setup.php.inc';
set_include_path(TESTDIR);
$c = \PEAR2\Pyrus\Config::singleton(TESTDIR, TESTDIR . '/plugins/pearconfig.xml');
restore_include_path();
$c->saveConfig();
$chan = new \PEAR2\Pyrus\Channel(new \PEAR2\Pyrus\ChannelFile(dirname(__DIR__).'/../sample_channel.xml'));
$c->channelregistry->add($chan);
$test->assertEquals(true, $c->channelregistry->exists('pear.unl.edu', true), 'external channel using full name');
$test->assertEquals(false, $c->channelregistry->exists('unl', true), 'external channel alias should fail strict check');
?>
===DONE===
--CLEAN--
<?php
include __DIR__ . '/../../../clean.php.inc';
?>
--EXPECT--
===DONE===