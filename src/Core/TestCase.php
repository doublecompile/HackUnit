<?hh //strict
namespace HackPack\HackUnit\Core;

use HackPack\HackUnit\Exception\MalformedSuite;
use HackPack\HackUnit\Exception\MarkTestAsSkipped;

abstract class TestCase implements TestInterface
{
    final public function __construct()
    {
    }

    public function expect<T>(T $context): Expectation<T>
    {
        return new Expectation($context);
    }

    public function expectCallable((function(): void) $callable): CallableExpectation
    {
        return new CallableExpectation($callable);
    }

    public function skip(): void
    {
        $this->markAsSkipped();
    }

    public function markAsSkipped(string $message = "Skipped"): void
    {
        throw new MarkTestAsSkipped($message);
    }

    public function markAsMalformed(): void
    {
        throw new MalformedSuite('All HackUnit specific methods must accept zero arguments and return void.');
    }

    final public function run(TestResult $result, \ReflectionMethod $test): void
    {
        try {
            $result->testStarted();
            $test->invoke($this);
            $result->testPassed();
        } catch(MarkTestAsSkipped $e) {
            $result->testSkipped();
        } catch (\Exception $e) {
            $result->testFailed($e);
        }
    }
}
