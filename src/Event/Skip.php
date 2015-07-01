<?hh // strict

namespace HackPack\HackUnit\Event;

use HackPack\HackUnit\Util\TraceItem;

<<IgnoreCoverage>>
class Skip
{
    public function __construct(private string $reason, private TraceItem $callSite)
    {
    }

    public function message() : string
    {
        return $this->reason;
    }

    public function assertionLine() : ?int
    {
        return $this->callSite['line'] === -1 ?
            null :
            $this->callSite['line'];
    }

    public function testMethod() : ?string
    {
        return $this->callSite['function'] === '' ?
            null :
            $this->callSite['function'];
    }

    public function testClass() : ?string
    {
        return $this->callSite['class'] === '' ?
            null :
            $this->callSite['class'];
    }

    public function testFile() : ?string
    {
        return $this->callSite['file'] === '' ?
            null :
            $this->callSite['file'];
    }
}

