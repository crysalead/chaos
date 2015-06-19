<?php
namespace chaos\spec\fixture\model;

class Gallery extends \chaos\model\Model
{
    protected static $_schema = 'chaos\source\database\Schema';

    protected static function _meta()
    {
        return ['source' => 'gallery'];
    }

    protected static function _schema($schema)
    {
        $schema->set('id', ['type' => 'serial']);
        $schema->set('name', ['type' => 'string']);

        $schema->bind('image', [
            'relation'    => 'hasMany',
            'to'          => 'chaos\spec\fixture\model\Image',
            'key'         => 'gallery_id'
        ]);
    }
}
