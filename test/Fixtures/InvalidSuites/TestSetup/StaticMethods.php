<?hh // strict

namespace HackPack\HackUnit\Tests\Fixtures\InvalidSuites\TestSetup;

class StaticMethods
{
    <<Setup>>
    public static function noStatics() : void
    {
    }

    <<Test>>
    public function test(\HackPack\HackUnit\Assert $assert) : void
    {
    }
}
