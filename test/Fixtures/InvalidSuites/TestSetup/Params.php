<?hh // strict

namespace HackPack\HackUnit\Tests\Fixtures\InvalidSuites\TestSetup;

<<TestSuite>>
class SetupParams
{
    <<Setup>>
    public function setup(string $required) : void
    {
    }

    <<Test>>
    public function test(\HackPack\HackUnit\Assertion\AssertionBuilder $assert) : void
    {
    }
}