<?hh // strict

namespace HackPack\HackUnit\Test;

use HackPack\HackUnit\Contract\Assert;
use HackPack\HackUnit\Contract\Test\TestCase;
use HackPack\HackUnit\Contract\Test\Suite;
use HackPack\HackUnit\Event\MalformedSuite;
use HackPack\HackUnit\Event\MalformedSuiteListener;
use HackPack\HackUnit\Util\Trace;
use FredEmmott\DefinitionFinder\TreeParser;
use FredEmmott\DefinitionFinder\FileParser;

final class Loader implements \HackPack\HackUnit\Contract\Test\Loader
{
    private int $testCount = 0;

    public function __construct(
        private (function(\ReflectionClass):Suite) $suiteBuilder,
        private Set<string> $includes = Set{},
        private Set<string> $excludes = Set{},
        private Vector<MalformedSuiteListener> $malformedListeners = Vector{},
    )
    {
    }

    public function onMalformedSuite(MalformedSuiteListener $listener) : this
    {
        $this->malformedListeners->add($listener);
        return $this;
    }

    public function including(string $path) : this
    {
        if (false !== ($fullPath = realpath($path))) {
            $this->includes->add($fullPath);
        }
        return $this;
    }

    public function excluding(string $path) : this
    {
        if (false !== ($fullPath = realpath($path))) {
            $this->excludes->add($fullPath);
        }
        return $this;
    }

    public function testSuites() : Vector<Suite>
    {
        $suites = Vector{};

        foreach ($this->includes as $path) {
            if (is_file($path)) {
                $scanner = FileParser::FromFile($path);
            } else {
                $scanner = TreeParser::FromPath($path);
            }
            $classes = $scanner->getClasses();

            foreach ($classes as $scannedClass) {
                // Check if ingoring
                $classFilename = $scannedClass->getFileName();
                $excludes = $this->excludes->filter($excludePath ==> {
                    if ($excludePath === $classFilename) {
                        return true;
                    } else {
                        return $excludePath . '/' === substr($classFilename, 0, strlen($excludePath) + 1);
                    }
                });
                if (!$excludes->isEmpty()) {
                    continue;
                }

                if (!class_exists($scannedClass->getName())) {
                    $this->load($classFilename);
                }

                try {
                    $classMirror = new \ReflectionClass($scannedClass->getName());
                } catch (\ReflectionException $e) {
                    // Unable to load the file, or the map was wrong?
                    // Should we warn the user?
                    continue;
                }

                $suite = $this->buildSuite($classMirror);

                if($suite !== null) {
                    $suites->add($suite);
                }

            }
        }

        if($suites->isEmpty()) {
            // No test suites?  Did they mark them?
            // TODO
        }

        return $suites;
    }

    private function buildSuite(\ReflectionClass $classMirror) : ?Suite
    {
        $className = $classMirror->getName();
        $classFile = $classMirror->getFileName();
        if( ! is_string($className) || ! is_string($classFile)) {
            // Reflector unable to figure the class name/file
            // This indicates something is wrong, so conservatively do not
            // load this suite
            return null;
        }

        $constructor = $classMirror->getConstructor();
        if( $constructor !== null && $constructor->getNumberOfRequiredParameters() > 0) {
            // Test suites must never require params to be constructed.
            $this->emitMalformedSuite(MalformedSuite::badMethod(
                $constructor,
                'Test suite classes must not require parameters in their constructors.'
            ));
            return null;
        }

        $this->testCount = 0;
        $methods = (new Vector($classMirror->getMethods()))
            ->filter($m ==> {
                $attrs = new Map($m->getAttributes());
                if($attrs->containsKey('Test')) {
                    $this->testCount += 1;
                }
                return
                    $attrs->containsKey('Test') ||
                    $attrs->containsKey('Setup') ||
                    $attrs->containsKey('TearDown');
            });

        if($this->testCount === 0) {
            $traceItem = Trace::buildItem([
                'file' => $classMirror->getFileName(),
                'class' => $classMirror->name,
                'line' => $classMirror->getStartLine(),
            ]);
            $reason = 'No test methods were found.  Did you forget to mark them with <<Test>>?';
            $this->emitMalformedSuite(new MalformedSuite(
                $traceItem,
                $reason,
            ));
            return null;
        }

        $builder = $this->suiteBuilder;
        $suite = $builder($classMirror);
        foreach($methods as $methodMirror) {
            if(
                $methodMirror->isAbstract() ||
                $methodMirror->isConstructor() ||
                $methodMirror->isDestructor() ||
                $methodMirror->isStatic()
            ) {
                // Must be normal instance method
                $this->emitMalformedSuite(MalformedSuite::badMethod(
                    $methodMirror,
                    'Setup, TearDown, and Test methods must be instance methods and must not be the constructor, the destructor, nor be abstract.',
                ));
                return null;
            }

            $isSuiteSetup = $this->isSuiteSetup($methodMirror);
            if($isSuiteSetup === null) {
                return null;
            }
            if($isSuiteSetup) {
                $suite->registerSuiteSetup($methodMirror);
            }

            $isSuiteTeardown = $this->isSuiteTeardown($methodMirror);
            if($isSuiteTeardown === null) {
                return null;
            }
            if($isSuiteTeardown) {
                $suite->registerSuiteTeardown($methodMirror);
            }

            $isTestSetup = $this->isTestSetup($methodMirror);
            if($isTestSetup === null) {
                return null;
            }
            if($isTestSetup) {
                $suite->registerTestSetup($methodMirror);
            }

            $isTestTeardown = $this->isTestTeardown($methodMirror);
            if($isTestTeardown === null) {
                return null;
            }
            if($isTestTeardown) {
                $suite->registerTestTeardown($methodMirror);
            }

            if($this->isTest($methodMirror)) {
                $suite->registerTestMethod($methodMirror);
            }
        }

        return $suite;
    }

    private function isSuiteSetup(\ReflectionMethod $methodMirror) : ?bool
    {
        // Need to mark with <<Setup('suite')>>
        $setup = $methodMirror->getAttribute('Setup');
        if(! is_array($setup) || array_search('suite', $setup) === false) {
            return false;
        }

        // No parameters
        if($methodMirror->getNumberOfRequiredParameters() !== 0) {
            $this->emitMalformedSuite(MalformedSuite::badMethod(
                $methodMirror,
                'Setup methods must not require parameters.',
            ));
            return null;
        }

        return true;
    }

    private function isTestSetup(\ReflectionMethod $methodMirror) : ?bool
    {
        // Need to mark with <<Setup('test')>> or <<Setup>>
        $setup = $methodMirror->getAttribute('Setup');
        if(
            ! is_array($setup) ||
            (count($setup) > 0 && array_search('suite', $setup) === false)
        ) {
            return false;
        }

        // No parameters
        if($methodMirror->getNumberOfRequiredParameters() !== 0) {
            $this->emitMalformedSuite(MalformedSuite::badMethod(
                $methodMirror,
                'Setup methods must not require parameters.',
            ));
            return null;
        }

        return true;
    }

    private function isSuiteTeardown(\ReflectionMethod $methodMirror) : ?bool
    {
        // Need to mark with <<TearDown('suite')>>
        $teardown = $methodMirror->getAttribute('TearDown');
        if(
            ! is_array($teardown) ||
            array_search('suite', $teardown) === false
        ) {
            return false;
        }

        // No parameters
        if($methodMirror->getNumberOfRequiredParameters() !== 0) {
            $this->emitMalformedSuite(MalformedSuite::badMethod(
                $methodMirror,
                'Tear down methods must not require parameters.',
            ));
            return null;
        }

        return true;
    }

    private function isTestTeardown(\ReflectionMethod $methodMirror) : ?bool
    {
        // Need to mark with <<TearDown('test')>> or <<TearDown>>
        $teardown = $methodMirror->getAttribute('TearDown');
        if(
            ! is_array($teardown) ||
            (count($teardown) > 0 && array_search('test', $teardown) === false)
        ) {
            return false;
        }

        // No parameters
        if($methodMirror->getNumberOfRequiredParameters() !== 0) {
            $this->emitMalformedSuite(MalformedSuite::badMethod(
                $methodMirror,
                'Teardown methods must not require parameters.',
            ));
            return null;
        }

        return true;
    }

    private function isTest(\ReflectionMethod $methodMirror) : bool
    {
        // Look for <<Test>> attribute
        if($methodMirror->getAttribute('Test') === null) {
            return false;
        }

        // Ensure method takes an Assert as the only parameter
        $params = new Vector($methodMirror->getParameters());

        if($params->count() !== 1) {
            $this->emitMalformedSuite(MalformedSuite::badMethod(
                $methodMirror,
                'Test methods must accept exactly 1 parameter of type HackPack\HackUnit\Contract\Assert',
            ));
            return false;
        }

        if($params->at(0)->getTypeText() !== Assert::class) {
            $this->emitMalformedSuite(MalformedSuite::badMethod(
                $methodMirror,
                'Test methods must accept exactly 1 parameter of type HackPack\HackUnit\Contract\Assert',
            ));
            return false;
        }

        return true;
    }

    private function load(string $fileName) : void
    {
        // Is there a better way of dynamically including files?
        /* HH_FIXME[1002] */
        require_once($fileName);
    }

    private function emitMalformedSuite(MalformedSuite $event) : void
    {
        foreach($this->malformedListeners as $l) {
            $l($event);
        }
    }
}
