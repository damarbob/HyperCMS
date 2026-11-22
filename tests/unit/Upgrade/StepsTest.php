<?php

use App\Libraries\Upgrade\Convert\ModelFields;
use App\Libraries\Upgrade\Steps\ConvertModelFields;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * What the step classes decide without a database.
 *
 * @internal
 */
final class StepsTest extends CIUnitTestCase
{
    public function testADefinitionThatHasBeenConvertedIsRecognised(): void
    {
        $old = [['id' => 'a', 'type' => 'text'], ['id' => 'b', 'type' => 'textarea'], ['id' => 'c', 'type' => 'number', 'options' => ['step' => 1]], ['id' => 'd', 'type' => 'file']];
        $this->assertFalse(ConvertModelFields::isVersion2($old));

        // A converted definition always shows itself, even when every field has a type the old form also had.
        $this->assertTrue(ConvertModelFields::isVersion2([['id' => 'n', 'type' => 'number', 'integer' => false, 'widget' => 'input']]));
        $this->assertTrue(ConvertModelFields::isVersion2([['id' => 'f', 'type' => 'file', 'multiple' => false, 'accept' => 'any']]));
        $this->assertTrue(ConvertModelFields::isVersion2([['id' => 'c', 'type' => 'code', 'language' => 'html']]));
        $this->assertTrue(ConvertModelFields::isVersion2([['id' => 'b', 'type' => 'longtext']]));
        $this->assertFalse(ConvertModelFields::isVersion2([['id' => 't', 'type' => 'text'], 'not a field']));
    }

    public function testConvertingADefinitionTwiceNeverLosesAnOption(): void
    {
        $converter = new ModelFields(App\Fields\FieldTypes::core());
        $old       = [['id' => 'n', 'type' => 'number', 'options' => ['step' => '0.01', 'min' => '1']], ['id' => 'f', 'type' => 'file', 'multiple' => true], ['id' => 't', 'type' => 'text']];

        $once = $converter->convert($old)['fields'];

        $this->assertTrue(ConvertModelFields::isVersion2($once), 'The second pass leaves it alone, so no option is read from the old keys again.');
        $this->assertSame(0.01, $once[0]['step']);
        $this->assertTrue($once[1]['multiple']);
    }
}
