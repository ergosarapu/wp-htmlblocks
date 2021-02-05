<?php

namespace HTMLBlocks;

use Carbon_Fields\Field\Field as CFField;

class Field
{
    private array $fieldConfig;

    private CFFIeld $cfField;

    public function __construct(array $fieldConfig)
    {
        $this->fieldConfig = $fieldConfig;
        $this->cfField = $this->makeField();
    }

    private function makeField(): CFField
    {
        $cfField = CFField::make(
            $this->fieldConfig['type'],
            $this->fieldConfig['name'],
            $this->fieldConfig['label']
        );

        // Call field config functions
        if (array_key_exists('config', $this->fieldConfig)) {
            $functions = $this->fieldConfig['config'];
            foreach ($functions as $function) {
                call_user_func_array([$cfField, $function['function']], $function['args']);
            }
        }

        return $cfField;
    }

    /**
     * Get the value of cfField
     */
    public function getCfField()
    {
        return $this->cfField;
    }
}
