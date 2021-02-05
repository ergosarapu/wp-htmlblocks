<?php

namespace HTMLBlocks\Tests;

use Carbon_Fields\Field\Association_Field;
use Carbon_Fields\Field\Text_Field;
use HTMLBlocks\Field;
use Symfony\Component\Yaml\Yaml;

final class FieldTest extends CFTest
{
    public function testMakeAssociationField()
    {
        $configYml = <<<EOT
        type: association
        name: posts
        label: Posts
        config:
            - function: set_types
              args:
                - - type: post
                    post_type: page
                  - type: post
                    post_type: post
            - function: set_min
              args:
                - 1
            - function: set_max
              args:
                - 2
        EOT;
        $fieldConfig = Yaml::parse($configYml);
        $field = new Field($fieldConfig);
        /** @var $cf_field Association_Field $a */
        $cfField = $field->getCfField();
        $this->assertInstanceOf(Association_Field::class, $cfField);
        $this->assertEquals($fieldConfig['config'][0]['args'][0], $cfField->get_types());
        $this->assertEquals($fieldConfig['config'][1]['args'][0], $cfField->get_min());
        $this->assertEquals($fieldConfig['config'][2]['args'][0], $cfField->get_max());
    }

    public function testMakeTextField()
    {
        $configYml = <<<EOT
        type: text
        name: phone
        label: Text
        config:
            - function: set_attribute
              args:
                - placeholder
                - (***) ***-****
        EOT;
        $fieldConfig = Yaml::parse($configYml);
        $field = new Field($fieldConfig);
        /** @var $cf_field Text_Field $a */
        $cfField = $field->getCfField();
        $this->assertInstanceOf(Text_Field::class, $cfField);
        $this->assertEquals(
            $fieldConfig['config'][0]['args'][1],
            $cfField->get_attribute($fieldConfig['config'][0]['args'][0])
        );
    }
}
