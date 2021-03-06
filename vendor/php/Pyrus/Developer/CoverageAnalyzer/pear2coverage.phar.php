<?php
function __autoload($class)
{
    $class = str_replace("Pyrus\Developer\CoverageAnalyzer", "", $class);
    include "phar://" . __FILE__ . str_replace("\\", "/", $class) . ".php";
}
Phar::webPhar("pear2coverage.phar.php");
echo "This phar is a web application, run within your web browser to use\n";
exit -1;
__HALT_COMPILER(); ?>
�                    Web/Controller.phpT  ��KT  ��l��         Web/View.phpB  ��KB  њ�'�         Web/Aggregator.php  ��K  W�	�         Web/Exception.phph   ��Kh   Or�V�         SourceFile.php�  ��K�  �S�+�         Aggregator.php	  ��K	  	r跶         Exception.phpd   ��Kd   s��U�      
   Sqlite.phpuu  ��Kuu  U� �         SourceFile/PerTest.php   ��K   ;x��      	   cover.css�  ��K�  e��s�      	   index.php�   ��K�   �Z'E�      <?php
namespace Pyrus\Developer\CoverageAnalyzer\Web {
class Controller {
    protected $view;
    protected $sqlite;
    protected $rooturl;

    function __construct(View $view, $rooturl)
    {
        $this->view = $view;
        $view->setController($this);
        $this->rooturl = $rooturl;
    }

    function route()
    {
        if (!isset($_SESSION['fullpath']) || isset($_GET['restart'])) {
            unset($_SESSION['fullpath']);
            if (isset($_GET['setdatabase'])) {
                if (file_exists($_GET['setdatabase'])) {
                    try {
                        $this->sqlite = new Aggregator($_GET['setdatabase']);
                        $_SESSION['fullpath'] = $_GET['setdatabase'];
                        return $this->view->TOC($this->sqlite);
                    } catch (\Exception $e) {
                        echo $e->getMessage() . '<br />';
                        // fall through
                    }
                }
            }
            return $this->getDatabase();
        } else {
            try {
                $this->sqlite = new Aggregator($_SESSION['fullpath']);
                if (isset($_GET['test'])) {
                    if ($_GET['test'] === 'TOC') {
                        return $this->view->testTOC($this->sqlite);
                    }
                    if (isset($_GET['file'])) {
                        return $this->view->fileCoverage($this->sqlite, $_GET['file'], $_GET['test']);
                    }
                    return $this->view->testTOC($this->sqlite, $_GET['test']);
                }
                if (isset($_GET['file'])) {
                    if (isset($_GET['line'])) {
                        return $this->view->fileLineTOC($this->sqlite, $_GET['file'], $_GET['line']);
                    }
                    return $this->view->fileCoverage($this->sqlite, $_GET['file']);
                }
            } catch (\Exception $e) {
                echo $e->getMessage() . '<br \>';
            }
            return $this->view->TOC($this->sqlite);
        }
    }

    function getFileLink($file, $test = null, $line = null)
    {
        if ($line) {
            return $this->rooturl . '?file=' . urlencode($file) . '&line=' . $line;
        }
        if ($test) {
            return $this->rooturl . '?file=' . urlencode($file) . '&test=' . $test;
        }
        return $this->rooturl . '?file=' . urlencode($file);
    }

    function getTOCLink($test = false)
    {
        if ($test === true) {
            return $this->rooturl . '?test=TOC';
        }
        if ($test) {
            return $this->rooturl . '?test=' . urlencode($test);
        }
        return $this->rooturl;
    }

    function getLogoutLink()
    {
        return $this->rooturl . '?restart=1';
    }

    function getDatabase()
    {
        $this->sqlite = $this->view->getDatabase();
    }
}
}
?>
<?php
namespace Pyrus\Developer\CoverageAnalyzer\Web;
use Pyrus\Developer\CoverageAnalyzer\SourceFile;
/**
 * Takes a source file and outputs HTML source highlighting showing the
 * number of hits on each line, highlights un-executed lines in red
 */
class View
{
    protected $savePath;
    protected $testPath;
    protected $sourcePath;
    protected $source;
    protected $controller;

    function getDatabase()
    {
        $output = new \XMLWriter;
        if (!$output->openUri('php://output')) {
            throw new Exception('Cannot open output - this should never happen');
        }
        $output->startElement('html');
         $output->startElement('head');
          $output->writeElement('title', 'Enter a path to the database');
         $output->endElement();
         $output->startElement('body');
          $output->writeElement('h2', 'Please enter the path to a coverage database');
          $output->startElement('form');
           $output->writeAttribute('name', 'getdatabase');
           $output->writeAttribute('method', 'GET');
           $output->writeAttribute('action', $this->controller->getTOCLink());
           $output->startElement('input');
            $output->writeAttribute('size', '90');
            $output->writeAttribute('type', 'text');
            $output->writeAttribute('name', 'setdatabase');
           $output->endElement();
           $output->startElement('input');
            $output->writeAttribute('type', 'submit');
           $output->endElement();
          $output->endElement();
         $output->endElement();
        $output->endElement();
        $output->endDocument();
    }

    function setController($controller)
    {
        $this->controller = $controller;
    }

    function logoutLink(\XMLWriter $output)
    {
        $output->startElement('h5');
         $output->startElement('a');
          $output->writeAttribute('href', $this->controller->getLogoutLink());
          $output->text('Current database: ' . $_SESSION['fullpath'] . '.  Click to start over');
         $output->endElement();
        $output->endElement();
    }

    function TOC($sqlite)
    {
        $coverage = $sqlite->retrieveProjectCoverage();
        $this->renderSummary($sqlite, $sqlite->retrievePaths(), false, $coverage[1], $coverage[0], $coverage[2]);
    }

    function testTOC($sqlite, $test = null)
    {
        if ($test) {
            return $this->renderTestCoverage($sqlite, $test);
        }
        $this->renderTestSummary($sqlite);
    }

    function fileLineTOC($sqlite, $file, $line)
    {
        $source = new SourceFile($file, $sqlite, $sqlite->testpath, $sqlite->codepath);
        return $this->renderLineSummary($file, $line, $sqlite->testpath, $source->getLineLinks($line));
    }

    function fileCoverage($sqlite, $file, $test = null)
    {
        if ($test) {
            $source = new SourceFile\PerTest($file, $sqlite, $sqlite->testpath, $sqlite->codepath, $test);
        } else {
            $source = new SourceFile($file, $sqlite, $sqlite->testpath, $sqlite->codepath);
        }
        return $this->render($source, $test);
    }

    function mangleFile($path, $istest = false)
    {
        return $this->controller->getFileLink($path, $istest);
    }

    function mangleTestFile($path)
    {
        return $this->controller->getTOClink($path);
    }

    function getLineLink($name, $line)
    {
        return $this->controller->getFileLink($name, null, $line);
    }

    function renderLineSummary($name, $line, $testpath, $tests)
    {
        $output = new \XMLWriter;
        if (!$output->openUri('php://output')) {
            throw new Exception('Cannot render ' . $name . ' line ' . $line . ', opening XML failed');
        }
        $output->setIndentString(' ');
        $output->setIndent(true);
        $output->startElement('html');
        $output->startElement('head');
        $output->writeElement('title', 'Tests covering line ' . $line . ' of ' . $name);
        $output->startElement('link');
        $output->writeAttribute('href', 'cover.css');
        $output->writeAttribute('rel', 'stylesheet');
        $output->writeAttribute('type', 'text/css');
        $output->endElement();
        $output->endElement();
        $output->startElement('body');
        $this->logoutLink($output);
        $output->writeElement('h2', 'Tests covering line ' . $line . ' of ' . $name);
        $output->startElement('p');
        $output->startElement('a');
        $output->writeAttribute('href', $this->controller->getTOCLink());
        $output->text('Aggregate Code Coverage for all tests');
        $output->endElement();
        $output->endElement();
        $output->startElement('p');
        $output->startElement('a');
        $output->writeAttribute('href', $this->mangleFile($name));
        $output->text('File ' . $name . ' code coverage');
        $output->endElement();
        $output->endElement();
        $output->startElement('ul');
        foreach ($tests as $testfile) {
            $output->startElement('li');
            $output->startElement('a');
            $output->writeAttribute('href', $this->mangleTestFile($testfile));
            $output->text(str_replace($testpath . '/', '', $testfile));
            $output->endElement();
            $output->endElement();
        }
        $output->endElement();
        $output->endElement();
        $output->endDocument();
    }

    /**
     * @param Pyrus\Developer\CodeCoverage\SourceFile $source
     * @param string $istest path to test file this is covering, or false for aggregate
     */
    function render(SourceFile $source, $istest = false)
    {
        $output = new \XMLWriter;
        if (!$output->openUri('php://output')) {
            throw new Exception('Cannot render ' . $source->shortName() . ', opening XML failed');
        }
        $output->setIndent(false);
        $output->startElement('html');
        $output->text("\n ");
        $output->startElement('head');
        $output->text("\n  ");
        if ($istest) {
            $output->writeElement('title', 'Code Coverage for ' . $source->shortName() . ' in ' .
                                  str_replace($source->testpath() . DIRECTORY_SEPARATOR, '', $istest));
        } else {
            $output->writeElement('title', 'Code Coverage for ' . $source->shortName());
        }
        $output->text("\n  ");
        $output->startElement('link');
        $output->writeAttribute('href', 'cover.css');
        $output->writeAttribute('rel', 'stylesheet');
        $output->writeAttribute('type', 'text/css');
        $output->endElement();
        $output->text("\n  ");
        $output->endElement();
        $output->text("\n ");
        $output->startElement('body');
        $output->text("\n ");
        $this->logoutLink($output);
        if ($istest) {
            $output->writeElement('h2', 'Code Coverage for ' . $source->shortName() . ' in ' .
                                  str_replace($source->testpath() . DIRECTORY_SEPARATOR, '', $istest));
        } else {
            $output->writeElement('h2', 'Code Coverage for ' . $source->shortName());
        }
        $output->text("\n ");
        $output->writeElement('h3', 'Coverage: ' . $source->coveragePercentage() . '% (Covered lines / Executable lines)');
        $info = $source->coverageInfo();
        $sourceCode = $source->source();

        $total = count($sourceCode);
        $output->writeRaw('<p><strong>' . $total . '</strong> total lines, of which <strong>' . $info[1] . '</strong> are executable, <strong>' . $info[2] .'</strong> are dead and <strong>' . ($total - $info[2] - $info[1]) . '</strong> are non-executable lines</p>');
        $output->writeRaw('<p>Of those <strong>' . $info[1] . '</strong> executable lines there are <strong>' . $info[0] . '</strong> lines covered with tests and <strong>' . ($info[1] - $info[0]) . '</strong> lack coverage</p>');
        $output->text("\n ");
        $output->startElement('p');
        $output->startElement('a');
        $output->writeAttribute('href', $this->controller->getTOCLink());
        $output->text('Aggregate Code Coverage for all tests');
        $output->endElement();
        $output->endElement();
        $output->startElement('pre');

        foreach ($sourceCode as $num => $line) {
            $coverage = $source->coverage($num);

            $output->startElement('span');
            $output->writeAttribute('class', 'ln');
            $output->text(str_pad($num, 8, ' ', STR_PAD_LEFT));
            $output->endElement();

            if ($coverage === false) {
                $output->text(str_pad(': ', 13, ' ', STR_PAD_LEFT) . $line);
                continue;
            }

            $output->startElement('span');
            $cov = is_array($coverage) ? $coverage['coverage'] : $coverage;
            if ($cov === -2) {
                $output->writeAttribute('class', 'dead');
                $output->text('           ');
            } elseif ($cov < 1) {
                $output->writeAttribute('class', 'nc');
                $output->text('           ');
            } else {
                $output->writeAttribute('class', 'cv');
                if (!$istest) {
                    $output->startElement('a');
                    $output->writeAttribute('href', $this->getLineLink($source->name(), $num));
                }

                $text = is_string($coverage) ? $coverage : $coverage['link'];
                $output->text(str_pad($text, 10, ' ', STR_PAD_LEFT) . ' ');
                if (!$istest) {
                    $output->endElement();
                }
            }

            $output->text(': ' .  $line);
            $output->endElement();
        }

        $output->endElement();
        $output->text("\n ");
        $output->endElement();
        $output->text("\n ");
        $output->endElement();
        $output->endDocument();
    }

    function renderSummary(Aggregator $agg, array $results, $istest = false, $total = 1, $covered = 1, $dead = 1)
    {
        $output = new \XMLWriter;
        if (!$output->openUri('php://output')) {
            throw new Exception('Cannot render test summary, opening XML failed');
        }
        $output->setIndentString(' ');
        $output->setIndent(true);
        $output->startElement('html');
        $output->startElement('head');
        if ($istest) {
            $output->writeElement('title', 'Code Coverage Summary [' . $istest . ']');
        } else {
            $output->writeElement('title', 'Code Coverage Summary');
        }
        $output->startElement('link');
        $output->writeAttribute('href', 'cover.css');
        $output->writeAttribute('rel', 'stylesheet');
        $output->writeAttribute('type', 'text/css');
        $output->endElement();
        $output->endElement();
        $output->startElement('body');
        if ($istest) {
            $output->writeElement('h2', 'Code Coverage Files for test ' . $istest);
        } else {
            $output->writeElement('h2', 'Code Coverage Files');
            $output->writeElement('h3', 'Total lines: ' . $total . ', covered lines: ' . $covered . ', dead lines: ' . $dead);
            $percent = 0;
            if ($total > 0) {
                $percent = round(($covered / $total) * 100, 1);
            }
            $output->startElement('p');
            if ($percent < 50) {
                $output->writeAttribute('class', 'bad');
            } elseif ($percent < 75) {
                $output->writeAttribute('class', 'ok');
            } else {
                $output->writeAttribute('class', 'good');
            }
            $output->text($percent . '% code coverage');
            $output->endElement();
        }
        $this->logoutLink($output);
        $output->startElement('p');
        $output->startElement('a');
        $output->writeAttribute('href', $this->controller->getTOCLink(true));
        $output->text('Code Coverage per PHPT test');
        $output->endElement();
        $output->endElement();
        $output->startElement('ul');
        foreach ($results as $i => $name) {
            $output->flush();
            $source = new SourceFile($name, $agg, $agg->testpath, $agg->codepath, null, false);
            $output->startElement('li');
            $percent = $source->coveragePercentage();
            $output->startElement('div');
            if ($percent < 50) {
                $output->writeAttribute('class', 'bad');
            } elseif ($percent < 75) {
                $output->writeAttribute('class', 'ok');
            } else {
                $output->writeAttribute('class', 'good');
            }
            $output->text(' Coverage: ' . str_pad($percent . '%', 4, ' ', STR_PAD_LEFT));
            $output->endElement();
            $output->startElement('a');
            $output->writeAttribute('href', $this->mangleFile($name, $istest));
            $output->text($source->shortName());
            $output->endElement();
            $output->endElement();
        }
        $output->endElement();
        $output->endElement();
        $output->endDocument();
    }

    function renderTestSummary(Aggregator $agg)
    {
        $output = new \XMLWriter;
        if (!$output->openUri('php://output')) {
                throw new Exception('Cannot render tests summary, opening XML failed');
        }
        $output->setIndentString(' ');
        $output->setIndent(true);
        $output->startElement('html');
        $output->startElement('head');
        $output->writeElement('title', 'Test Summary');
        $output->startElement('link');
        $output->writeAttribute('href', 'cover.css');
        $output->writeAttribute('rel', 'stylesheet');
        $output->writeAttribute('type', 'text/css');
        $output->endElement();
        $output->endElement();
        $output->startElement('body');
        $this->logoutLink($output);
        $output->writeElement('h2', 'Tests Executed, click for code coverage summary');
        $output->startElement('p');
        $output->startElement('a');
        $output->writeAttribute('href', $this->controller->getTOClink());
        $output->text('Aggregate Code Coverage for all tests');
        $output->endElement();
        $output->endElement();
        $output->startElement('ul');
        foreach ($agg->retrieveTestPaths() as $test) {
            $output->startElement('li');
            $output->startElement('a');
            $output->writeAttribute('href', $this->mangleTestFile($test));
            $output->text(str_replace($agg->testpath . '/', '', $test));
            $output->endElement();
            $output->endElement();
        }
        $output->endElement();
        $output->endElement();
        $output->endDocument();
    }

    function renderTestCoverage(Aggregator $agg, $test)
    {
        $reltest = str_replace($agg->testpath . '/', '', $test);
        $output = new \XMLWriter;
        if (!$output->openUri('php://output')) {
            throw new Exception('Cannot render test ' . $reltest . ' coverage, opening XML failed');
        }
        $output->setIndentString(' ');
        $output->setIndent(true);
        $output->startElement('html');
        $output->startElement('head');
        $output->writeElement('title', 'Code Coverage Summary for test ' . $reltest);
        $output->startElement('link');
        $output->writeAttribute('href', 'cover.css');
        $output->writeAttribute('rel', 'stylesheet');
        $output->writeAttribute('type', 'text/css');
        $output->endElement();
        $output->endElement();
        $output->startElement('body');
        $this->logoutLink($output);
        $output->writeElement('h2', 'Code Coverage Files for test ' . $reltest);
        $output->startElement('ul');
        $paths = $agg->retrievePathsForTest($test);
        foreach ($paths as $name) {
            $source = new SourceFile\PerTest($name, $agg, $agg->testpath, $agg->codepath, $test);
            $output->startElement('li');
            $percent = $source->coveragePercentage();
            $output->startElement('div');
            if ($percent < 50) {
                $output->writeAttribute('class', 'bad');
            } elseif ($percent < 75) {
                $output->writeAttribute('class', 'ok');
            } else {
                $output->writeAttribute('class', 'good');
            }
            $output->text(' Coverage: ' . str_pad($source->coveragePercentage() . '%', 4, ' ', STR_PAD_LEFT));
            $output->endElement();
            $output->startElement('a');
            $output->writeAttribute('href', $this->mangleFile($name, $test));
            $output->text($source->shortName());
            $output->endElement();
            $output->endElement();
        }
        $output->endElement();
        $output->endElement();
        $output->endDocument();
    }
}
<?php
namespace Pyrus\Developer\CoverageAnalyzer\Web {
use Pyrus\Developer\CoverageAnalyzer;
class Aggregator extends CoverageAnalyzer\Aggregator
{
    public $codepath;
    public $testpath;
    protected $sqlite;
    public $totallines = 0;
    public $totalcoveredlines = 0;

    /**
     * @var string $testpath Location of .phpt files
     * @var string $codepath Location of code whose coverage we are testing
     */
    function __construct($db = ':memory:')
    {
        $this->sqlite = new CoverageAnalyzer\Sqlite($db);
        $this->codepath = $this->sqlite->codepath;
        $this->testpath = $this->sqlite->testpath;
    }

    function retrieveLineLinks($file)
    {
        return $this->sqlite->retrieveLineLinks($file);
    }

    function retrievePaths()
    {
        return $this->sqlite->retrievePaths();
    }

    function retrievePathsForTest($test)
    {
        return $this->sqlite->retrievePathsForTest($test);
    }

    function retrieveTestPaths()
    {
        return $this->sqlite->retrieveTestPaths();
    }

    function coveragePercentage($sourcefile, $testfile = null)
    {
        return $this->sqlite->coveragePercentage($sourcefile, $testfile);
    }

    function coverageInfo($path)
    {
        return $this->sqlite->retrievePathCoverage($path);
    }

    function coverageInfoByTest($path, $test)
    {
        return $this->sqlite->retrievePathCoverageByTest($path, $test);
    }

    function retrieveCoverage($path)
    {
        return $this->sqlite->retrieveCoverage($path);
    }

    function retrieveProjectCoverage()
    {
        return $this->sqlite->retrieveProjectCoverage();
    }

    function retrieveCoverageByTest($path, $test)
    {
        return $this->sqlite->retrieveCoverageByTest($path, $test);
    }
}
}
?>
<?php
namespace Pyrus\Developer\CoverageAnalyzer\Web {
class Exception extends \Exception {}
}
?>
<?php
namespace Pyrus\Developer\CoverageAnalyzer;
class SourceFile
{
    protected $source;
    protected $path;
    protected $sourcepath;
    protected $coverage;
    protected $aggregator;
    protected $testpath;
    protected $linelinks;

    function __construct($path, Aggregator $agg, $testpath, $sourcepath, $coverage = true)
    {
        $this->source = file($path);
        $this->path = $path;
        $this->sourcepath = $sourcepath;

        array_unshift($this->source, '');
        unset($this->source[0]); // make source array indexed by line number

        $this->aggregator = $agg;
        $this->testpath = $testpath;
        if ($coverage === true) {
            $this->setCoverage();
        }
    }

    function setCoverage()
    {
        $this->coverage = $this->aggregator->retrieveCoverage($this->path);
    }

    function aggregator()
    {
        return $this->aggregator;
    }

    function testpath()
    {
        return $this->testpath;
    }

    function render(AbstractSourceDecorator $decorator = null)
    {
        if ($decorator === null) {
            $decorator = new DefaultSourceDecorator('.');
        }
        return $decorator->render($this);
    }

    function coverage($line = null)
    {
        if ($line === null) {
            return $this->coverage;
        }

        if (!isset($this->coverage[$line])) {
            return false;
        }

        return $this->coverage[$line];
    }

    function coveragePercentage()
    {
        return $this->aggregator->coveragePercentage($this->path);
    }

    function coverageInfo()
    {
        return $this->aggregator->coverageInfo($this->path);
    }

    function name()
    {
        return $this->path;
    }

    function shortName()
    {
        return str_replace($this->sourcepath . DIRECTORY_SEPARATOR, '', $this->path);
    }

    function source()
    {
        $cov = $this->coverage();
        if (empty($cov)) {
            return $this->source;
        }

        /* Make sure we have as many lines as required
         * Sometimes Xdebug returns coverage on one line beyond what
         * our file has, this is PHP doing a return on the file.
         */
        $endLine = max(array_keys($cov));
        if (count($this->source) < $endLine) {
            // Add extra new line if required since we use <pre> to format
            $secondLast = $endLine - 1;
            $this->source[$secondLast] = str_replace("\r", '', $this->source[$secondLast]);
            $len = strlen($this->source[$secondLast]) - 1;
            if (substr($this->source[$secondLast], $len) != "\n") {
                $this->source[$secondLast] .= "\n";
            }

            $this->source[$endLine] = "\n";
        }

        return $this->source;
    }

    function coveredLines()
    {
        $info = $this->aggregator->coverageInfo($this->path);
        return $info[0];
    }

    function getLineLinks($line)
    {
        if (!isset($this->linelinks)) {
            $this->linelinks = $this->aggregator->retrieveLineLinks($this->path);
        }

        if (isset($this->linelinks[$line])) {
            return $this->linelinks[$line];
        }

        return false;
    }
}<?php
namespace Pyrus\Developer\CoverageAnalyzer {
class Aggregator
{
    protected $codepath;
    protected $testpath;
    protected $sqlite;
    public $totallines = 0;
    public $totalcoveredlines = 0;

    /**
     * @var string $testpath Location of .phpt files
     * @var string $codepath Location of code whose coverage we are testing
     */
    function __construct($testpath, $codepath, $db = ':memory:')
    {
        $newcodepath = realpath($codepath);
        if (!$newcodepath) {
            if (!strpos($codepath, '://') || !file_exists($codepath)) {
                // stream wrapper not found
                throw new Exception('Can not find code path ' . $codepath);
            }
        } else {
            $codepath = $newcodepath;
        }

        $files = array();
        foreach (new \RegexIterator(
                    new \RecursiveIteratorIterator(
                        new \RecursiveDirectoryIterator($codepath, 0|\RecursiveDirectoryIterator::SKIP_DOTS)
                    ),
                    '/\.php$/') as $file) {
            if (strpos((string) $file, '.svn') || strpos($testpath, (string)$file)) {
                continue;
            }

            $files[] = realpath((string) $file);
        }

        $this->sqlite = new Sqlite($db, $codepath, $testpath, $files);
        $this->codepath = $codepath;
        $this->sqlite->begin();
        echo "Scanning for xdebug coverage files...";
        $files = $this->scan($testpath);
        echo "done\n";
        $infostring = '';
        echo "Parsing xdebug results\n";
        if (!count($files)) {
            echo "done (no modified xdebug files)\n";
            return;
        }

        $delete = array();
        foreach ($files as $testid => $xdebugfile) {
            $phpt = str_replace('.xdebug', '.phpt', $xdebugfile);
            if (!file_exists($phpt)) {
                $delete[] = $xdebugfile;
                continue;
            }

            $id = $this->sqlite->addTest($phpt);
            echo '(' . $testid . ' of ' . count($files) . ') ' . $xdebugfile;
            $this->retrieveXdebug($xdebugfile, $id);
            echo "done\n";
        }

        echo "done\n";
        $this->sqlite->addNoCoverageFiles();
        $this->sqlite->updateAllLines();
        $this->sqlite->updateTotalCoverage();
        $this->sqlite->commit();

        if (count($delete)) {
            echo "\nWARNING: The following .xdebug files are outdated relics and should be deleted\n";
            foreach ($delete as $d) {
                echo "$d\n";
            }
        }
    }

    function retrieveLineLinks($file)
    {
        return $this->sqlite->retrieveLineLinks($file);
    }

    function retrievePaths()
    {
        return $this->sqlite->retrievePaths();
    }

    function retrievePathsForTest($test)
    {
        return $this->sqlite->retrievePathsForTest($test);
    }

    function retrieveTestPaths()
    {
        return $this->sqlite->retrieveTestPaths();
    }

    function coveragePercentage($sourcefile, $testfile = null)
    {
        return $this->sqlite->coveragePercentage($sourcefile, $testfile);
    }

    function coverageInfo($path)
    {
        return $this->sqlite->retrievePathCoverage($path);
    }

    function coverageInfoByTest($path, $test)
    {
        return $this->sqlite->retrievePathCoverageByTest($path, $test);
    }

    function retrieveCoverage($path)
    {
        return $this->sqlite->retrieveCoverage($path);
    }

    function retrieveCoverageByTest($path, $test)
    {
        return $this->sqlite->retrieveCoverageByTest($path, $test);
    }

    function retrieveProjectCoverage()
    {
        return $this->sqlite->retrieveProjectCoverage();
    }

    function retrieveXdebug($path, $testid)
    {
        $source = '$xdebug = ' . file_get_contents($path) . ";\n";
        eval($source);
        $this->sqlite->addCoverage(str_replace('.xdebug', '.phpt', $path), $testid, $xdebug);
    }

    function scan($testpath)
    {
        $a = $testpath;
        $testpath = realpath($testpath);
        if (!$testpath) {
            throw new Exception('Unable to process path' . $a);
        }

        $testpath = str_replace('\\', '/', $testpath);
        $this->testpath = $testpath;

        // get a list of all xdebug files
        $xdebugs = array();
        foreach (new \RegexIterator(
                                    new \RecursiveIteratorIterator(
                                        new \RecursiveDirectoryIterator($testpath,
                                                                        0|\RecursiveDirectoryIterator::SKIP_DOTS)),
                                    '/\.xdebug$/') as $file) {
            if (strpos((string) $file, '.svn')) {
                continue;
            }
            $xdebugs[] = realpath((string) $file);
        }
        echo count($xdebugs), ' total...';

        $unmodified = $modified = array();
        foreach ($xdebugs as $path) {
            if ($this->sqlite->unChangedXdebug($path)) {
                $unmodified[$path] = true;
                continue;
            }

            $modified[] = $path;
        }

        $xdebugs = $modified;
        sort($xdebugs);
        // index from 1
        array_unshift($xdebugs, '');
        unset($xdebugs[0]);
        $test = array_flip($xdebugs);
        foreach ($this->sqlite->retrieveTestPaths() as $path) {
            $xdebugpath = str_replace('.phpt', '.xdebug', $path);
            if (isset($test[$xdebugpath]) || isset($unmodified[$xdebugpath])) {
                continue;
            }

            // remove outdated tests
            echo "Removing results from $xdebugpath\n";
            $this->sqlite->removeOldTest($path);
        }

        return $xdebugs;
    }

    function render($toPath)
    {
        $decorator = new DefaultSourceDecorator($toPath, $this->testpath, $this->codepath);
        echo "Generating project coverage data...";
        $coverage = $this->sqlite->retrieveProjectCoverage();
        echo "done\n";
        $decorator->renderSummary($this, $this->retrievePaths(), $this->codepath, false, $coverage[1],
                                  $coverage[0], $coverage[2]);
        $a = $this->codepath;
        echo "[Step 2 of 2] Rendering per-test coverage...";
        $decorator->renderTestCoverage($this, $this->testpath, $a);
        echo "done\n";
    }
}
}
?>
<?php
namespace Pyrus\Developer\CoverageAnalyzer {
class Exception extends \Exception {}
}
?>
<?php
namespace Pyrus\Developer\CoverageAnalyzer;
class Sqlite
{
    protected $db;
    protected $totallines = 0;
    protected $coveredlines = 0;
    protected $deadlines = 0;
    protected $pathCovered = array();
    protected $pathTotal = array();
    protected $pathDead = array();
    public $codepath;
    public $testpath;

    private $statement;
    private $lines = array();
    private $files = array();

    const COVERAGE_COVERED      = 1;
    const COVERAGE_NOT_EXECUTED = 0;
    const COVERAGE_NOT_COVERED  = -1;
    const COVERAGE_DEAD         = -2;

    function __construct($path = ':memory:', $codepath = null, $testpath = null, $codefiles = array())
    {
        $this->files = $codefiles;
        $this->db = new \Sqlite3($path);
        $this->db->exec('PRAGMA temp_store=2');
        $this->db->exec('PRAGMA count_changes=OFF');

        $version = '5.3.0';
        $sql = 'SELECT version FROM analyzerversion';
        if (@$this->db->querySingle($sql) == $version) {
            $this->codepath = $this->db->querySingle('SELECT codepath FROM paths');
            $this->testpath = $this->db->querySingle('SELECT testpath FROM paths');
            return;
        }

        // restart the database
        echo "Upgrading database to version $version";
        if (!$codepath || !$testpath) {
            throw new Exception('Both codepath and testpath must be set in ' .
                                'order to initialize a coverage database');
        }

        $this->codepath = $codepath;
        $this->testpath = $testpath;
        $this->db->exec('DROP TABLE IF EXISTS coverage;');
        echo ".";
        $this->db->exec('DROP TABLE IF EXISTS coverage_nonsource;');
        echo ".";
        $this->db->exec('DROP TABLE IF EXISTS not_covered;');
        echo ".";
        $this->db->exec('DROP TABLE IF EXISTS files;');
        echo ".";
        $this->db->exec('DROP TABLE IF EXISTS tests;');
        echo ".";
        $this->db->exec('DROP TABLE IF EXISTS paths;');
        echo ".";
        $this->db->exec('DROP TABLE IF EXISTS coverage_per_file;');
        echo ".";
        $this->db->exec('DROP TABLE IF EXISTS line_info;');
        echo ".";
        $this->db->exec('DROP TABLE IF EXISTS all_lines;');
        echo ".";
        $this->db->exec('DROP TABLE IF EXISTS xdebugs;');
        echo ".";
        $this->db->exec('DROP TABLE IF EXISTS analyzerversion;');

        echo ".";
        $this->db->exec('BEGIN');

        $sql = '
            CREATE TABLE coverage (
              files_id integer NOT NULL,
              tests_id integer NOT NULL,
              linenumber INTEGER NOT NULL,
              state INTEGER NOT NULL,
              PRIMARY KEY (files_id, linenumber, tests_id)
            );

            CREATE INDEX idx_coveragestats  ON coverage (files_id, tests_id, state);
            CREATE INDEX idx_coveragestats2 ON coverage (files_id, linenumber, tests_id, state);
            CREATE INDEX idx_coveragestats3 ON coverage (files_id, tests_id);

            CREATE TABLE all_lines (
              files_id integer NOT NULL,
              linenumber INTEGER NOT NULL,
              state INTEGER NOT NULL,
              PRIMARY KEY (files_id, linenumber, state)
            );

             CREATE INDEX idx_all_lines_stats ON all_lines (files_id, linenumber);

            CREATE TABLE line_info (
              files_id integer NOT NULL,
              covered INTEGER NOT NULL,
              dead  INTEGER NOT NULL,
              total INTEGER NOT NULL,
              PRIMARY KEY (files_id)
            );
          ';
        $this->exec($sql);

        echo ".";
        $sql = '
          CREATE TABLE coverage_nonsource (
            files_id integer NOT NULL,
            tests_id integer NOT NULL,
            PRIMARY KEY (files_id, tests_id)
          );
          ';
        $this->exec($sql);

        echo ".";
        $sql = '
          CREATE TABLE files (
            id INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
            path TEXT(500) NOT NULL,
            hash TEXT(32) NOT NULL,
            issource BOOL NOT NULL,
            UNIQUE (path)
          );
          CREATE INDEX files_issource on files (issource);
          ';
        $this->exec($sql);

        echo ".";
        $sql = '
          CREATE TABLE xdebugs (
            path TEXT(500) NOT NULL,
            hash TEXT(32) NOT NULL,
            PRIMARY KEY (path)
          );';
        $this->exec($sql);

        echo ".";
        $sql = '
          CREATE TABLE tests (
            id INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
            testpath TEXT(500) NOT NULL,
            hash TEXT(32) NOT NULL,
            UNIQUE (testpath)
          );';
        $this->exec($sql);

        echo ".";
        $sql = '
          CREATE TABLE analyzerversion (
            version TEXT(5) NOT NULL
          );

          INSERT INTO analyzerversion VALUES("' . $version . '");

          CREATE TABLE paths (
            codepath TEXT NOT NULL,
            testpath TEXT NOT NULL
          );';
        $this->exec($sql);

        echo ".";
        $sql = '
          INSERT INTO paths VALUES(
            "' . $this->db->escapeString($codepath) . '",
            "' . $this->db->escapeString($testpath). '");';
        $this->exec($sql);
        $this->db->exec('COMMIT');
        echo "done\n";
    }

    public function exec($sql)
    {
        $worked = $this->db->exec($sql);
        if (!$worked) {
            @$this->db->exec('ROLLBACK');
            $error = $this->db->lastErrorMsg();
            throw new Exception('Unable to create Code Coverage SQLite3 database: ' . $error);
        }
    }

    function retrieveLineLinks($file, $id = null)
    {
        if ($id === null) {
            $id = $this->getFileId($file);
        }

        $sql = 'SELECT t.testpath, c.linenumber
            FROM
                coverage c, tests t
            WHERE
                c.files_id = ' . $id . ' AND t.id = c.tests_id';
        $result = $this->db->query($sql);
        if (!$result) {
            $error = $this->db->lastErrorMsg();
            throw new Exception('Cannot retrieve line links for ' . $file .
                                ' line #' . $line .  ': ' . $error);
        }

        $ret = array();
        while ($res = $result->fetchArray(SQLITE3_ASSOC)) {
            $ret[$res['linenumber']][] = $res['testpath'];
        }
        return $ret;
    }

    function retrieveTestPaths()
    {
        $sql = 'SELECT testpath from tests ORDER BY testpath';
        $result = $this->db->query($sql);
        if (!$result) {
            $error = $this->db->lastErrorMsg();
            throw new Exception('Cannot retrieve test paths :' . $error);
        }
        $ret = array();
        while ($res = $result->fetchArray(SQLITE3_NUM)) {
            $ret[] = $res[0];
        }
        return $ret;
    }

    function retrievePathsForTest($test, $all = 0)
    {
        $id = $this->getTestId($test);
        $ret = array();
        if ($all) {
            $sql = '
                SELECT DISTINCT path
                FROM coverage_nonsource c, files
                WHERE c.tests_id = ' . $id . '
                    AND files.id = c.files_id
                GROUP BY c.files_id
                ORDER BY path';
            $result = $this->db->query($sql);
            if (!$result) {
                $error = $this->db->lastErrorMsg();
                throw new Exception('Cannot retrieve file paths for test ' . $test . ':' . $error);
            }

            while ($res = $result->fetchArray(SQLITE3_NUM)) {
                $ret[] = $res[0];
            }
        }

        $sql = '
            SELECT DISTINCT path
            FROM coverage c, files
            WHERE
                c.tests_id = ' . $id . '
              AND
                files.id = c.files_id
            GROUP BY c.files_id
            ORDER BY path';
        $result = $this->db->query($sql);
        if (!$result) {
            $error = $this->db->lastErrorMsg();
            throw new Exception('Cannot retrieve file paths for test ' . $test . ':' . $error);
        }

        while ($res = $result->fetchArray(SQLITE3_NUM)) {
            $ret[] = $res[0];
        }

        return $ret;
    }

    function retrievePaths($all = 0)
    {
        if ($all) {
            $sql = 'SELECT path from files ORDER BY path';
        } else {
            $sql = 'SELECT path from files WHERE issource = 1 ORDER BY path';
        }

        $result = $this->db->query($sql);
        if (!$result) {
            $error = $this->db->lastErrorMsg();
            throw new Exception('Cannot retrieve file paths :' . $error);
        }

        $ret = array();
        while ($res = $result->fetchArray(SQLITE3_NUM)) {
            $ret[] = $res[0];
        }

        return $ret;
    }

    function coveragePercentage($sourcefile, $testfile = null)
    {
        if ($testfile) {
            $coverage = $this->retrievePathCoverageByTest($sourcefile, $testfile);
        } else {
            $coverage = $this->retrievePathCoverage($sourcefile);
        }

        if ($coverage[1]) {
            return round(($coverage[0] / $coverage[1]) * 100, 1);
        }

        return 0;
    }

    function retrieveProjectCoverage($path = null)
    {
        if ($this->totallines) {
            return array($this->coveredlines, $this->totallines, $this->deadlines);
        }

        $sql = '
            SELECT covered, total, dead, path
            FROM line_info, files
            WHERE files.id = line_info.files_id';
        if ($path !== null) {
            $sql .= ' AND files.path = "' . $this->db->escapeString($path) . '"';
        }

        $result = $this->db->query($sql);
        if (!$result) {
            $error = $this->db->lastErrorMsg();
            throw new Exception('Cannot retrieve coverage for ' . $path.  ': ' . $error);
        }

        while ($res = $result->fetchArray(SQLITE3_ASSOC)) {
            $this->pathTotal[$res['path']]   = $res['total'];
            $this->pathCovered[$res['path']] = $res['covered'];
            $this->pathDead[$res['path']]    = $res['dead'];
            $this->coveredlines += $res['covered'];
            $this->totallines   += $res['total'];
            $this->deadlines    += $res['dead'];
        }

        return array($this->coveredlines, $this->totallines, $this->deadlines);
    }

    function retrievePathCoverage($path)
    {
        if (!$this->totallines) {
            // set up the cache
            $this->retrieveProjectCoverage($path);
        }

        if (!isset($this->pathCovered[$path])) {
            return array(0, 0, 0);
        }

        return array($this->pathCovered[$path], $this->pathTotal[$path], $this->pathDead[$path]);
    }

    function retrievePathCoverageByTest($path, $test)
    {
        $id = $this->getFileId($path);
        $testid = $this->getTestId($test);

        $sql = '
            SELECT state, COUNT(linenumber) AS ln
            FROM coverage
            WHERE files_id = ' . $id. ' AND tests_id = ' . $testid . '
            GROUP BY state';
        $result = $this->db->query($sql);
        if (!$result) {
            $error = $this->db->lastErrorMsg();
            throw new Exception('Cannot retrieve path coverage for ' . $path .
                                ' in test ' . $test . ': ' . $error);
        }

        $total = $dead = $covered = 0;
        while ($res = $result->fetchArray(SQLITE3_ASSOC)) {
            if ($res['state'] === Sqlite::COVERAGE_COVERED) {
                $covered = $res['ln'];
            }

            if ($res['state'] === Sqlite::COVERAGE_DEAD) {
                $dead = $res['ln'];
            }

            $total += $res['ln'];
        }

        return array($covered, $total, $dead);
    }

    function retrieveCoverageByTest($path, $test)
    {
        $id = $this->getFileId($path);
        $testid = $this->getTestId($test);

        $sql = 'SELECT state AS coverage, linenumber FROM coverage
                    WHERE files_id = ' . $id . ' AND tests_id = ' . $testid . '
                    ORDER BY linenumber ASC';
        $result = $this->db->query($sql);
        if (!$result) {
            $error = $this->db->lastErrorMsg();
            throw new Exception('Cannot retrieve test ' . $test .
                                ' coverage for ' . $path.  ': ' . $error);
        }

        $ret = array();
        while ($res = $result->fetchArray(SQLITE3_ASSOC)) {
            $ret[$res['linenumber']] = $res['coverage'];
        }

        return $ret;
    }

    function getFileId($path)
    {
        $sql = 'SELECT id FROM files WHERE path = "' . $this->db->escapeString($path) .'"';
        $id = $this->db->querySingle($sql);
        if ($id === false || $id === null) {
            throw new Exception('Unable to retrieve file ' . $path . ' id from database');
        }

        return $id;
    }

    function getTestId($path)
    {
        $sql = 'SELECT id FROM tests WHERE testpath = "' . $this->db->escapeString($path) . '"';
        $id = $this->db->querySingle($sql);
        if ($id === false || $id === null) {
            throw new Exception('Unable to retrieve test file ' . $path . ' id from database');
        }

        return $id;
    }

    function removeOldTest($testpath, $id = null)
    {
        if ($id === null) {
            $id = $this->getTestId($testpath);
        }

        echo "deleting old test ", $testpath,'.';
        $this->db->exec('DELETE FROM tests WHERE id = ' . $id);
        echo '.';
        $this->db->exec('DELETE FROM coverage WHERE tests_id = ' . $id);
        echo '.';
        $this->db->exec('DELETE FROM coverage_nonsource WHERE tests_id = ' . $id);
        echo '.';
        $this->db->exec('DELETE FROM xdebugs WHERE path = "' .
                        $this->db->escapeString(str_replace('.phpt', '.xdebug', $testpath)) . '"');
        echo "done\n";
    }

    function addTest($testpath, $id = null)
    {
        try {
            $id = $this->getTestId($testpath);
            $this->db->exec('UPDATE tests SET hash = "' . md5_file($testpath) . '" WHERE id = ' . $id);
        } catch (Exception $e) {
            echo "Adding new test $testpath\n";
            $sql = 'INSERT INTO tests (testpath, hash) VALUES(:testpath, :md5)';
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':testpath', $testpath);
            $stmt->bindValue(':md5', md5_file($testpath));
            $stmt->execute();
            $id = $this->db->lastInsertRowID();
        }

        $file  = str_replace('.phpt', '.xdebug', $testpath);
        $sql = 'REPLACE INTO xdebugs (path, hash) VALUES(:path, :md5)';
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':path', $file);
        $stmt->bindValue(':md5', md5_file($file));
        $stmt->execute();

        return $id;
    }

    function unChangedXdebug($path)
    {
        $sql = 'SELECT hash FROM xdebugs WHERE path = "' . $this->db->escapeString($path) . '"';
        $md5 = $this->db->querySingle($sql);
        if (!$md5 || $md5 != md5_file($path)) {
            return false;
        }

        return true;
    }

    function retrieveCoverage($path)
    {
        $id = $this->getFileId($path);
        $links = $this->retrieveLineLinks($path, $id);
        $links = array_map(function ($arr) {return count($arr);}, $links);

        $sql = '
            SELECT state AS coverage, linenumber
            FROM all_lines
            WHERE files_id = ' . $id . '
            ORDER BY linenumber ASC';
        $result = $this->db->query($sql);
        if (!$result) {
            $error = $this->db->lastErrorMsg();
            throw new Exception('Cannot retrieve coverage for ' . $path.  ': ' . $error);
        }

        $return = array();
        while ($res = $result->fetchArray()) {
            if (!isset($return[$res['linenumber']])) {
                $return[$res['linenumber']] = array();
            }

            if (
                !isset($return[$res['linenumber']]['coverage']) ||
                $return[$res['linenumber']]['coverage'] !== Sqlite::COVERAGE_COVERED
            ) {
                // Found a case where a line could be dead and not covered, we still don't know why
                if (
                    isset($return[$res['linenumber']]['coverage']) &&
                    $return[$res['linenumber']]['coverage'] === Sqlite::COVERAGE_NOT_COVERED &&
                    $res['coverage'] === Sqlite::COVERAGE_DEAD
                ) {
                    continue;
                }

                $return[$res['linenumber']]['coverage'] = $res['coverage'];
            }


            if (isset($links[$res['linenumber']])) {
                $return[$res['linenumber']]['link'] = $links[$res['linenumber']];
            } else {
                $return[$res['linenumber']]['link'] = 0;
            }
        }

        return $return;
    }

    function updateTotalCoverage()
    {
        echo "Updating coverage per-file intermediate table\n";

        $sql = '
            SELECT files_id, linenumber, state
            FROM all_lines
            ORDER BY files_id, linenumber ASC';
        $result = $this->db->query($sql);
        $lines = array();
        while ($res = $result->fetchArray(SQLITE3_ASSOC)) {
            if (!isset($lines[$res['files_id']])) {
                $lines[$res['files_id']] = array();
            }

            $lines[$res['files_id']][$res['linenumber']] = $res['state'];
        }

        $ret = array();
        foreach ($lines as $file => $lines) {
            $ret[$file]['covered']     = 0;
            $ret[$file]['dead']        = 0;
            $ret[$file]['not_covered'] = 0;
            foreach (array_count_values($lines) as $state => $count) {
                if ($state === Sqlite::COVERAGE_COVERED) {
                    $ret[$file]['covered'] = $count;
                }

                if ($state === Sqlite::COVERAGE_NOT_COVERED) {
                    $ret[$file]['not_covered'] = $count;
                }

                if ($state === Sqlite::COVERAGE_DEAD) {
                    $ret[$file]['dead'] = $count;
                }
            }
        }

        foreach ($ret as $id => $line) {
            $covered     = $line['covered'];
            $dead        = $line['dead'];
            $not_covered = $line['not_covered'];
            $this->db->exec('REPLACE INTO line_info (files_id, covered, dead, total)
                            VALUES(' . $id . ',' . $covered . ',' . $dead . ',' . ($covered + $not_covered) . ')');
            echo ".";
        }

        echo "\ndone\n";
    }

    public function updateAllLines()
    {
        echo "Updating the all lines internal table\n";

        $keys = implode(', ', array_keys($this->lines));
        $sql = '
            SELECT files_id, linenumber, state
            FROM all_lines
            WHERE files_id IN (' . $keys . ')
            ORDER BY linenumber ASC';

        $result = $this->db->query($sql);
        $data = array();
        while ($res = $result->fetchArray(SQLITE3_ASSOC)) {
            if (!isset($data[$res['files_id']])) {
                $data[$res['files_id']] = array();
            }

            $data[$res['files_id']][$res['linenumber']] = $res['state'];
        }

        foreach ($data as $id => $lines) {
            foreach ($lines as $line => $state) {
                if (
                    // Only allow lines that are in the new rollout.
                    isset($this->lines[$id][$line]) ||
                    // Line already marked as covered.
                    (isset($this->lines[$id][$line]) && $this->lines[$id][$line] !== 1)
                ) {
                    $this->lines[$id][$line] = $state;
                }
            }
        }
        unset($data);

        echo '.';
        $sql  = 'DELETE FROM all_lines WHERE files_id IN (' . $keys . ');';
        $this->db->exec($sql);

        $sql = 'INSERT INTO all_lines (files_id, linenumber, state) VALUES (:id, :line, :state);';
        $stmt = $this->db->prepare($sql);
        foreach ($this->lines as $file => $lines) {
            if (!is_array($lines)) {
                continue;
            }

            echo '.';
            foreach ($lines as $line => $state) {
                $stmt->bindValue(':id',    $file,  SQLITE3_INTEGER);
                $stmt->bindValue(':line',  $line,  SQLITE3_INTEGER);
                $stmt->bindValue(':state', $state, SQLITE3_INTEGER);
                $stmt->execute();
            }
        }

        echo "\ndone\n";
    }

    function addFile($path, $issource = 0)
    {
        $sql = 'SELECT id FROM files WHERE path = "' . $this->db->escapeString($path) . '"';
        $id = $this->db->querySingle($sql);
        if ($id === false) {
            throw new Exception('Unable to add file ' . $path . ' to database');
        }

        if ($id !== null) {
            $sql = 'UPDATE files SET hash = :md5, issource = :issource WHERE path = :path';
        } else {
            $sql = 'INSERT INTO files (path, hash, issource) VALUES(:path, :md5, :issource)';
        }

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':path',     $path);
        $stmt->bindValue(':md5',      md5_file($path));
        $stmt->bindValue(':issource', $issource);
        if (!$stmt->execute()) {
            throw new Exception('Problem running this particular SQL: ' . $sql);
        }

        if ($id === null) {
            $id = $this->db->lastInsertRowID();
        }

        return $id;
    }

    public function addNoCoverageFiles()
    {
        echo "Adding files with no coverage information\n";
        foreach ($this->files as $file) {
            echo "$file\n";
            $id = $this->addFile($file, 1);

            // Figure out of the file has been already inclduded or not
            $included = false;

            $class = str_replace(array($this->codepath, '.php'), '', $file);
            $class = 'pear2' . str_replace('/', '\\', $class);

            $classes = array_merge(get_declared_classes(), get_declared_interfaces());
            if (in_array($class, $classes)) {
                $included = true;
            }

            // Get basic coverage information on the file
            if ($included === false) {
                xdebug_start_code_coverage(XDEBUG_CC_UNUSED | XDEBUG_CC_DEAD_CODE);
                if (!class_exists($class) && !interface_exists($class)) {
                    include $file;
                }
                $data = xdebug_get_code_coverage(true);
                $this->lines[$id] = $data[$file];
            }
        }

        echo "Done\n";
    }

    function addCoverage($testpath, $testid, $xdebug)
    {
        $sql = 'DELETE FROM coverage WHERE tests_id = ' . $testid . ';
                DELETE FROM coverage_nonsource WHERE tests_id = ' . $testid;
        $worked = $this->db->exec($sql);

        echo "\n";
        foreach ($xdebug as $path => $results) {
            if (!file_exists($path)) {
                continue;
            }

            $issource = 1;
            if (
                strpos($path, $this->codepath) !== 0 ||
                strpos($path, $this->testpath) === 0
            ) {
                $issource = 0;
            }

            echo ".";
            $id = $this->addFile($path, $issource);
            $key = array_search($path, $this->files);
            if (isset($this->files[$key])) {
                unset($this->files[$key]);
            }

            if ($issource) {
                if (!isset($this->lines[$id])) {
                    $this->lines[$id] = array();
                }
            } elseif (!$issource) {
                $sql2 = 'INSERT INTO coverage_nonsource
                        (files_id, tests_id)
                        VALUES(' . $id . ', ' . $testid . ')';
                $worked = $this->db->exec($sql2);
                if (!$worked) {
                    $error = $this->db->lastErrorMsg();
                    throw new Exception('Cannot add coverage for test ' . $testpath .
                                        ', covered file ' . $path . ': ' . $error);
                }
                continue;
            }

            $sql = '';
            foreach ($results as $line => $state) {
                if (!$line) {
                    continue; // line 0 does not exist, skip this (xdebug quirk)
                }

                if ($issource) {
                    if (
                        !isset($this->lines[$id][$line]) ||
                        // Line already marked as covered.
                        $this->lines[$id][$line] !== 1
                    ) {
                        $this->lines[$id][$line] = $state;
                    }
                }

                $sql .= 'INSERT INTO coverage
                    (files_id, tests_id, linenumber, state)
                    VALUES (' . $id . ', ' . $testid . ', ' . $line . ', ' . $state. ');';
            }

            if ($sql !== '') {
                $worked = $this->db->exec($sql);
                if (!$worked) {
                    $error = $this->db->lastErrorMsg();
                    throw new Exception('Cannot add coverage for test ' . $testpath .
                                        ', covered file ' . $path . ': ' . $error . "\nSQL: $sql");
                }
            }
        }
    }

    function begin()
    {
        $this->db->exec('PRAGMA synchronous=OFF'); // make inserts super fast
        $this->db->exec('BEGIN');
    }

    function commit()
    {
        $this->db->exec('COMMIT');
        $this->db->exec('PRAGMA synchronous=NORMAL'); // make inserts super fast
        echo "Compatcing the database\n";
        $this->db->exec('VACUUM');
    }

    /**
     * Retrieve a list of .phpt tests that either have been modified,
     * or the files they access have been modified
     * @return array
     */
    function getModifiedTests()
    {
        // first scan for new .phpt files
        $tests = array();
        foreach (new \RegexIterator(
                                    new \RecursiveIteratorIterator(
                                        new \RecursiveDirectoryIterator($this->testpath,
                                                                        0|\RecursiveDirectoryIterator::SKIP_DOTS)),
                                    '/\.phpt$/') as $file) {
            if (strpos((string) $file, '.svn')) {
                continue;
            }

            $tests[] = realpath((string) $file);
        }

        $newtests = array();
        foreach ($tests as $path) {
            if ($path == $this->db->querySingle('SELECT testpath FROM tests WHERE testpath = "' .
                                       $this->db->escapeString($path) . '"')) {
                continue;
            }

            $newtests[] = $path;
        }

        $modifiedTests = $modifiedPaths = array();
        $paths = $this->retrievePaths(1);
        echo "Scanning ", count($paths), " source files";
        foreach ($paths as $path) {
            echo '.';

            $sql = 'SELECT id, hash, issource FROM files WHERE path = "' . $this->db->escapeString($path) . '"';
            $result = $this->db->query($sql);
            while ($res = $result->fetchArray(SQLITE3_ASSOC)) {
                if (!file_exists($path) || md5_file($path) == $res['hash']) {
                    if ($res['issource'] && !file_exists($path)) {
                        $this->db->exec('
                            DELETE FROM files WHERE id = '. $res['id'] .';
                            DELETE FROM coverage WHERE files_id = '. $res['id'] . ';
                            DELETE FROM all_lines WHERE files_id = '. $res['id'] . ';
                            DELETE FROM line_info WHERE files_id = '. $res['id'] . ';');
                    }
                    break;
                }

                $modifiedPaths[] = $path;
                // file is modified, get a list of tests that execute this file
                if ($res['issource']) {
                    $sql = '
                        SELECT t.testpath
                        FROM coverage c, tests t
                        WHERE
                            c.files_id = ' . $res['id'] . '
                          AND
                            t.id = c.tests_id';
                } else {
                    $sql = '
                        SELECT t.testpath
                        FROM coverage_nonsource c, tests t
                        WHERE
                            c.files_id = ' . $res['id'] . '
                          AND
                            t.id = c.tests_id';
                }

                $result2 = $this->db->query($sql);
                while ($res = $result2->fetchArray(SQLITE3_NUM)) {
                    $modifiedTests[$res[0]] = true;
                }

                break;
            }
        }

        echo "done\n";
        echo count($modifiedPaths), ' modified files resulting in ',
            count($modifiedTests), " modified tests\n";
        $paths = $this->retrieveTestPaths();
        echo "Scanning ", count($paths), " test paths";
        foreach ($paths as $path) {
            echo '.';
            $sql = '
                SELECT id, hash FROM tests where testpath = "' .
                $this->db->escapeString($path) . '"';
            $result = $this->db->query($sql);
            while ($res = $result->fetchArray(SQLITE3_ASSOC)) {
                if (!file_exists($path)) {
                    $this->removeOldTest($path, $res['id']);
                    continue;
                }

                if (md5_file($path) != $res['hash']) {
                    $modifiedTests[$path] = true;
                }
            }
        }

        echo "done\n";
        echo count($newtests), ' new tests and ', count($modifiedTests), " modified tests should be re-run\n";
        return array_merge($newtests, array_keys($modifiedTests));
    }
}
<?php
namespace Pyrus\Developer\CoverageAnalyzer\SourceFile {
use Pyrus\Developer\CoverageAnalyzer\Aggregator,
    Pyrus\Developer\CoverageAnalyzer\AbstractSourceDecorator;
class PerTest extends \Pyrus\Developer\CoverageAnalyzer\SourceFile
{
    protected $testname;

    function __construct($path, Aggregator $agg, $testpath, $sourcepath, $testname, $coverage =  true)
    {
        $this->testname = $testname;
        parent::__construct($path, $agg, $testpath, $sourcepath, $coverage);
    }

    function setCoverage()
    {
        $this->coverage = $this->aggregator->retrieveCoverageByTest($this->path, $this->testname);
    }

    function coveredLines()
    {
        $info = $this->aggregator->coverageInfoByTest($this->path, $this->testname);
        return $info[0];
    }

    function render(AbstractSourceDecorator $decorator = null)
    {
        if ($decorator === null) {
            $decorator = new DefaultSourceDecorator('.');
        }
        return $decorator->render($this, $this->testname);
    }

    function coveragePercentage()
    {
        return $this->aggregator->coveragePercentage($this->path, $this->testname);
    }

    function coverageInfo()
    {
        return $this->aggregator->coverageInfoByTest($this->path, $this->testname);
    }
}
}
?>

.ln {background-color:#f6bd0f; padding-right: 4px;}
.cv {background-color:#afd8f8;}
.nc {background-color:#d64646;}
.dead {background-color:#ff8e46;}

ul { list-style-type: none; }

div.bad, div.ok, div.good {
    white-space:pre;
    font-family:courier;
    width: 160px;
    float: left;
    margin-right: 10px;
}
.bad {background-color:#d64646; }
.ok {background-color:#f6bd0f; }
.good {background-color:#588526;}
<?php
namespace Pyrus\Developer\CoverageAnalyzer {
session_start();
$view = new Web\View;
$rooturl = parse_url($_SERVER["REQUEST_URI"]);
$rooturl = $rooturl["path"];
$controller = new Web\Controller($view, $rooturl);
$controller->route();
}srԆ1��#��ń�G\�   GBMB