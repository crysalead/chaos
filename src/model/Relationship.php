<?php
namespace chaos\model;

use Countable;
use chaos\SourceException;
use set\Set;

/**
 * The `Relationship` class encapsulates the data and functionality necessary to link two model
 * classes together.
 */
class Relationship
{
    /**
     * A one-to-one or many-to-one relationship in which a key contains an ID value linking to
     * another document or record.
     */
    const LINK_KEY = 'key';

    /**
     * A many-to-many relationship in which a key contains an embedded array of IDs linking to other
     * records or documents.
     */
    const LINK_KEY_LIST = 'keylist';

    /**
     * A relationship linking type defined by one document or record (or multiple) being embedded
     * within another.
     */
    const LINK_EMBEDDED = 'embedded';

    /**
     * The reciprocal of `LINK_EMBEDDED`, this defines a linking type wherein an embedded document
     * references the document that contains it.
     */
    const LINK_CONTAINED = 'contained';

    /**
     * The relationship configuration.
     *
     * @var array
     */
    public $_config = [];

    /**
     * Constructs an object that represents a relationship between two model classes.
     *
     * @param array $config The relationship's configuration, which defines how the two models in
     *                      question are bound. The available options are:
     *                      - `'name'`        _string_ : The name of the field used when accessing the related
     *                                                   data in a result set. For example, in the case of `Posts hasMany Comments`, the
     *                                                   field name defaults to `'comments'`.
     *                      - `'keys'`        _mixed_  : An array of field that define the relationship, where the
     *                                                   keys is the key in the originating model, and the values is the key in the
     *                                                   target model.
     *                      - `'type'`        _string_ : The type of relationship. Should be one of `'belongsTo'`, `'hasOne'` or `'hasMany'`.
     *                      - `'from'`        _string_ : The fully namespaced class name this relationship originates.
     *                      - `'to'`          _string_ : The fully namespaced class name this relationship targets.
     *                      - `'through'`     _string_ : The fully namespaced class name this relationship transits.
     *                      - `'using'`       _string_ : The relation name to use in combinaison with through option.
     *                      - `'link'`        _string_ : A constant specifying how the object bound to the originating
     *                                                   model is linked to the object bound to the target model. For relational
     *                                                   databases, the only valid value is `LINK_KEY`, which means a foreign
     *                                                   key in one object matches another key (usually the primary key) in the other.
     *                                                   For document-oriented and other non-relational databases, different types of
     *                                                   linking, including key lists, database reference objects (such as MongoDB's
     *                                                   `MongoDBRef`), or even embedding.
     *                      - `'fields'`      _mixed_  : An array of the subset of fields that should be selected
     *                                                   from the related object(s) by default. If set to `true` (the default), all
     *                                                   fields are selected.
     *                      - `'constraints'` _mixed_  : A string or array containing additional constraints
     *                                                   on the relationship query. If a string, can contain a literal SQL fragment or
     *                                                   other database-native value. If an array, maps fields from the related object
     *                                                   either to fields elsewhere, or to arbitrary expressions. In either case, _the
     *                                                   values specified here will be literally interpreted by the database_.
     *                      - `'strategy'`    _closure_: An anonymous function used by an instantiating class,
     *                                                   such as a database object, to provide additional, dynamic configuration, after
     *                                                   the `Relationship` instance has finished configuring itself.
     *                      - `'conventions'` _object_ : The naming conventions instance.
     */
    public function __construct($config = [])
    {
        $defaults = [
            'name'        => null,
            'correlate'   => null,
            'keys'        => null,
            'type'        => null,
            'from'        => null,
            'to'          => null,
            'through'     => null,
            'using'       => null,
            'mode'        => 'diff',
            'link'        => static::LINK_KEY,
            'fields'      => true,
            'constraints' => [],
            'strategy'    => null,
            'conventions' => null
        ];
        $config += $defaults;

        foreach (['type', 'from', 'to'] as $value) {
            if (!$config[$value]) {
                throw new SourceException("Error, `'{$value}'` option can't be empty.");
            }
        }

        $conventions = $config['conventions'] = $config['conventions'] ?: new Conventions();

        if (!$config['through'] && $config['type'] === 'hasManyThrough') {
            throw new SourceException("Error, `'through'` option can't be empty for a has many through relation.");
        }

        if (!$config['keys']) {
            $primaryKey = $conventions->apply('primaryKey');
            if ($config['type'] === 'belongsTo') {
                $config['keys'][$conventions->apply('foreignKey', $config['from'])] = $primaryKey;
            } else {
                $config['keys'][$primaryKey] = $conventions->apply('foreignKey', $config['from']);
            }
        }

        if (!$config['correlate']) {
            $config['correlate'] = $conventions->apply('fieldName', $config['from']);
        }

        if (!$config['name']) {
            $config['name'] = $conventions->apply('fieldName', $config['to']);
        }

        if ($config['through'] && !$config['using']) {
            $config['using'] = $conventions->apply('usingName', $config['name']);
        }
        $this->_config = $config;
    }

    /**
     * Returns the named configuration item, or all configuration data, if no parameter is given.
     *
     * @param  string $name The name of the configuration item to return, or `null` to return all items.
     * @return mixed        Returns a single configuration item (mixed), or an array of all items.
     */
    public function data($name = null)
    {
        if (!$name) {
            return $this->_config;
        }
        return isset($this->_config[$name]) ? $this->_config[$name] : null;
    }

    /**
     * Allows relationship configuration items to be queried by name as methods.
     *
     * @param  string $name The name of the configuration item to query.
     * @param  array  $args Unused.
     * @return mixed        Returns the value of the given configuration item.
     */
    public function __call($name, $args = array())
    {
        return $this->data($name);
    }

    /**
     * Get a related object (or objects) for the given object connected to it by this relationship.
     *
     * @return boolean Returns `true` if the relationship is a `'hasMany'` or `'hasManyThrough`' relation,
     *                 returns `false` otherwise.
     */
    public function hasMany()
    {
        return $this->type() === "hasMany" || $this->type() === "hasManyThrough";
    }

    /**
     * Gets a related object (or objects) for the given object connected to it by this relationship.
     *
     * @param  object $entity  The object to get the related data for.
     * @param  array  $options Additional options to merge into the query to be performed, where
     *                         applicable.
     * @return object          Returns the object(s) for this relationship.
     */
    public function get($entity, $options = [])
    {
        $model = $this->to();
        $link = $this->link();
        $strategies = $this->_strategies();

        if (!isset($strategies[$link]) || !is_callable($strategies[$link])) {
            throw new SourceException("Attempted to get object for invalid relationship link type `{$link}`.");
        }
        return $strategies[$link]($entity, $this, $options);
    }

    /**
     * Generates query parameters for a related object (or objects) for the given object
     * connected to it by this relationship.
     *
     * @param  object $entity The entity or collection object to get the related data from.
     * @return array          Returns a conditions array.
     */
    public function query($entity)
    {
        list($from, $to) = each($this->keys());

        if (!$entity instanceof Countable) {
            if (!isset($entity->{$from})) {
                throw new SourceException("The `'{$from}'` key is missing from entity data.");
            }
            $conditions[$to] = $entity->{$from};
            return $conditions;
        }

        $class = $this->_config['from'];
        foreach ($entity as $key => $value) {
            if ($value instanceof $class) {
                $conditions[$to][] = $this->query($value);
            } else {
                $conditions[$to][] = $value;
            }
        }
        return $conditions;
    }

    /**
     * Strategies used to query related objects, indexed by key.
     */
    protected function _strategies()
    {
        $normalize = function($data, $rel) {
            if ($data) {
                return $data;
            }
            $to = $rel->to();
            if ($rel->hasMany()) {
                return $to::create(['type' => 'set']);
            }
            return $to::create();
        };

        $query = function($entity, $rel, $options) use ($normalize) {
            $query = [];

            if ($rel->through()) {
                $relThrough = $entity::relation($rel->through());
                $collections = $relThrough->query($entity)->get();
                $middle = $relThrough->from();
                $relTo = $middle::relation($rel->using());
                $to = $relTo->to();
                if (!$query['conditions'] = $relTo->query($collections)) {
                   return $normalize(null, $relTo);
                }
            } else {
                $to = $rel->to();
                if (!$query['conditions'] = $rel->query($entity)) {
                   return $normalize(null, $rel);
                }
            }

            $query['fields'] = $rel->fields();
            $query['constraints'] = $rel->constraints();

            return new Query([
                'type'   => $rel->hasMany() ? 'all' : 'first',
                'model' => $to,
                'query'  => Set::merge($query, $options)
            ]);
        };

        return [
            static::LINK_EMBEDDED => function($entity, $rel, $options) use ($normalize) {
                $name = $rel->name();
                $to = $rel->to();
                return $normalize($entity->{$name}, $rel);
            },
            static::LINK_CONTAINED => function($entity, $rel, $options) use ($normalize) {
                return $normalize($rel->hasMany() ? $entity->parent()->parent() : $entity->parent(), $rel);
            },
            static::LINK_KEY => $query,
            static::LINK_KEY_LIST  => $query
        ];
    }

    /**
     * Validates an entity relation.
     *
     * @param  object  $entity The relation's entity
     * @param  array   $options Validates option.
     * @return boolean
     */
    public function validates($entity, $options = [])
    {
        $defaults = ['with' => false];
        $name = $this->name();
        if (!isset($entity->{$name})) {
            return [true];
        }
        return (array) $entity->{$name}->validates($options + $defaults);
    }

    /**
     * Saving an entity relation.
     *
     * @param  object  $entity The relation's entity
     * @param  array   $options Saving options.
     * @return boolean
     */
    public function save($entity, $options = [])
    {
        if ($this->link() !== Relationship::LINK_KEY) {
            return true;
        }

        $name = $this->name();
        if (!isset($entity->{$name})) {
            return true;
        }
        $related = $entity->{$name};

        $result = false;
        switch ($this->type()){
            case 'hasManyThrough':
                $relThrough = $entity::relation($this->through());
                $association = $relThrough->query($entity);
                $middle = $relThrough->to();
                $using = $this->using();
                $relTo = $middle::relation($using);
                $strategy = '_' . $this->mode();
                $result = $this->{$strategy}($related, $relTo, $relThrough, $association, $options);
                unset($entity->{$using});
            case 'hasMany':
                $association = $this->query($entity);
                $to = $this->to();
                $erase = array_fill_keys(array_keys($association), null);
                $to::update($erase, $association);
                $result = $related->set($association)->save($options);
            break;
            case 'hasOne':
                $association = $this->query($entity);
                $result = !!$related->set($association)->save($options);
            break;
            case 'belongsTo':
                $result = $related->save($options);
                $association = $this->query($related);
                $entity->set($association);
            break;
        }
        return $result;
    }

    /**
     * Perform a HasMany Through diff saving (i.e only create/delete the unexisted/deleted associations)
     *
     * @param  object  $collection      The pivot collection.
     * @param  object  $relTo           The destination relation.
     * @param  object  $relVia          The middle relation.
     * @param  array   $associationFrom The association data extracted from the through relation.
     * @param  array   $options         Saving options.
     * @return boolean
     */
    protected function _diff($collection, $relTo, $relThrough, $associationFrom, $options = [])
    {
        $return = true;
        $to = $relTo->to();
        $middle = $relThrough->to();
        $alreadySaved = $middle::find('all', ['conditions' => $associationFrom]);

        foreach ($collection as $entity) {
            $finded = false;
            $entity->save($options);
            $associationTo = $relTo->query($entity);

            foreach ($alreadySaved as $key => $value) {
                if (!array_intersect_assoc($associationTo, $value)) {
                    continue;
                }
                $finded = true;
                unset($alreadySaved[$key]);
                break;
            }

            if (!$finded) {
                $item = $middle::create($associationFrom + $relTo->query($entity));
                $return &= $item->save($options);
            }
        }

        $toDelete = [];

        foreach ($alreadySaved as $key => $entity) {
            $toDelete[] = $entity->primaryKey();
        }

        if ($toDelete) {
            $primaryKey = $entity::schema()->primaryKey();
            $return &= $middle::remove([$primaryKey => $toDelete]);
        }
        return true;
    }

    /**
     * Perform a asMany Through flush saving (i.e remove & recreate all associations)
     *
     * @param  object  $collection      The pivot collection.
     * @param  object  $relTo           The destination relation.
     * @param  object  $relVia          The middle relation.
     * @param  array   $associationFrom The association data extracted from the through relation.
     * @param  array   $options         Saving options.
     * @return boolean
     */
    protected function _flush($collection, $relTo, $relThrough, $associationFrom, $options = [])
    {
        $return = true;
        $to = $relTo->to();
        $middle = $relThrough->to();
        $return &= $middle::remove($associationFrom);
        foreach ($collection as $entity) {
            $entity->save($options);
            $item = $middle::create($associationFrom + $relTo->query($entity));
            $return &= $item->save($options);
        }
        return $return;
    }

    /**
     * The `'with'` option formatter function
     *
     * @return array The formatter with array
     */
    public static function with($with)
    {
        if (!$with) {
            return  false;
        }
        if ($with === true) {
            $with = array_fill_keys(array_keys($this->relations()), true);
        } else {
            $with = Set::expand(Set::normalize((array) $with));
        }
        return $with;
    }
}
