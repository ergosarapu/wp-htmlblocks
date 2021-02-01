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
        $config_yml = <<<EOT
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
        $field_config = Yaml::parse($config_yml);
        $field = new Field($field_config);
        /** @var $cf_field Association_Field $a */
        $cf_field = $field->getCfField();
        $this->assertInstanceOf(Association_Field::class, $cf_field);
        $this->assertEquals($field_config['config'][0]['args'][0], $cf_field->get_types());
        $this->assertEquals($field_config['config'][1]['args'][0], $cf_field->get_min());
        $this->assertEquals($field_config['config'][2]['args'][0], $cf_field->get_max());
    }

    public function testMakeTextField()
    {
        $config_yml = <<<EOT
        type: text
        name: phone
        label: Text
        config:
            - function: set_attribute
              args:
                - placeholder
                - (***) ***-****
        EOT;
        $field_config = Yaml::parse($config_yml);
        $field = new Field($field_config);
        /** @var $cf_field Text_Field $a */
        $cf_field = $field->getCfField();
        $this->assertInstanceOf(Text_Field::class, $cf_field);
        $this->assertEquals(
            $field_config['config'][0]['args'][1],
            $cf_field->get_attribute($field_config['config'][0]['args'][0])
        );
    }
}
