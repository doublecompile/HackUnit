<?hh //strict
namespace HackPack\HackUnit\Tests\Error;

use HackPack\HackUnit\Core\TestCase;
use HackPack\HackUnit\Error\TraceParser;

class TraceParserTest extends TestCase
{
    <<test>>
    public function test_getOrigin_should_return_class_method_message_file_and_line(): void
    {
        $this->skip();
        //$line = __LINE__ + 1;
        //$parser = $this->getParser();
        //$info = $parser->getOrigin();
        //$this->expect($info['method'])->toEqual('HackPack\HackUnit\Tests\Error\TraceParserTest::test_getOrigin_should_return_class_method_message_file_and_line');
        //$this->expect($info['message'])->toEqual('Failure!');
        //$this->expect($info['location'])->toEqual(__FILE__ . ':' . $line);
    }

    //protected function getParser(): TraceParser
    //{
        //$this->skip();
        //$parser = null;
        //try {
            //throw new \Exception("Failure!");
        //} catch (\Exception $e) {
            //$parser = new TraceParser($e);
        //}
        //return $parser;
    //}
}
