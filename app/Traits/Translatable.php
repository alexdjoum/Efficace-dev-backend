<?php

namespace App\Traits;

trait Translatable
{
    protected $translatableAttributes = [];

    public function translate()
    {
        foreach ($this->translatableAttributes as $attribute) {
            if (isset($this->$attribute)) {
                $this->$attribute = __('attributes.' . $this->$attribute);
            }
        }
        
        return $this;
    }

    public function getTranslatedAttribute($attribute)
    {
        return __('attributes.' . $this->$attribute);
    }
}