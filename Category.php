<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;

class Category extends Model
{
    public $table = 'categories';
    protected $parentColumn = 'parent_id';
    protected $pathColumn = 'path';
    protected $newParent;
    protected $fillable = ['name'];
    protected $dateFormat = 'U';

    /**
     * Get the parent column name.
     *
     * @return string
     */
    public function getParentColumnName()
    {
        return $this->parentColumn;
    }

    /**
     * Get the parent column value.
     *
     * @return string
     */
    public function getParentColumnValue()
    {
        return $this->{$this->getParentColumnName()};
    }

    /**
     * Get the path column name.
     *
     * @return string
     */
    public function getPathColumnName()
    {
        return $this->pathColumn;
    }

    /**
     * Get the path column name.
     *
     * @param string $str
     * @return string
     */
    public function setPathColumn($newPath)
    {
        return $this->{$this->getPathColumnName()} = $newPath;
    }

    /**
     * @param string $str
     * @return string
     */
    public function getPathColumnValue($str = '')
    {
        return \Illuminate\Support\Arr::get($this->attributes, $this->getPathColumnName(), '') . $str;
    }

    /**
     * Parent relation (self-referential) 1-1.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function parent()
    {
        return $this->belongsTo(get_class($this), $this->getParentColumnName());
    }

    /**
     * Children relation (self-referential) 1-N.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function children()
    {
        return $this->hasMany(get_class($this), $this->getParentColumnName());
    }

    /**
     * Move childs of this to other category
     * @param $newParentId
     */
    public function newParent($newParentId)
    {
        $this->newParent = $newParentId;
    }

    protected static function boot()
    {
        parent::boot();
        static::created(function (Category $category) {
            $category->saveChildOf($category->parent);
        });

        static::deleted(function (Category $category) {
            $category->deleteNode();
        });
        if (property_exists('static', 'forceDeleting')) {
            static::restored(function (Category $category) {
                $category->restoreCateAndChild();
            });
        }
    }

    /**
     * Create root category
     *
     * @return bool
     * @throws \Exception
     */
    public function saveRoot()
    {
        return $this->saveChildOf(null);
    }

    /**
     * make this child of the destination category
     * @param $category
     * @return bool
     * @throws \Exception
     */
    public function saveChildOf($category)
    {
        if (is_null($category)) {
            $newParentPath = '';
            $newParentId = null;
        } else {
            if (is_numeric($category)) {
                if ($this->getParentColumnValue() == $category) {
                    return $this->save();
                }
                $category = $this->newQuery()->find($category);
                if (is_null($category)) {
                    throw new \Exception('New parent category not exists.');
                }
            }
            /**
             * @var Category $category
             */
            $newParentPath = $category->getPathColumnValue();
            $newParentId = $category->getKey();
        }
        if (!is_null($newParentId) && $this->isAncestorOf($category)) {
            throw new \Exception('Destination category is children of this.');
        }
        $oldPath = $this->getPathColumnValue();
        $newPath = sprintf('%s%s-', $newParentPath, $this->getKey());
        $this->{$this->getParentColumnName()} = $newParentId;
        $this->{$this->getPathColumnName()} = $newPath;
        if ($this->save() && !empty($oldPath)) {
            $this->newQuery()->where($this->getPathColumnName(), 'like', $oldPath . '%')->update([
                $this->getPathColumnName() => \DB::raw(sprintf('REPLACE(%s,"%s","%s")', $this->getPathColumnName(), $oldPath, $newPath))
            ]);
        }

        return true;
    }

    /**
     * Delete category and child or move child to new category
     * @return bool|mixed
     */
    public function deleteNode()
    {
        $childQuery = $this->newQuery();
        if (!is_null($this->newParent)) {
            $newParentQuery = $this->newQuery();
            $pathColumn = $this->getPathColumnName();
            $newParent = $newParentQuery->find($this->newParent, [$pathColumn, $this->getKeyName()]);
            if (!$newParent) {
                return false;
            }
            $childQuery->where($pathColumn, 'like', $this->getPathColumnValue('%'))->update([
                $this->getPathColumnName() => \DB::raw(sprintf('REPLACE(%s,"%s","%s")', $pathColumn, $this->getPathColumnValue(), $newParent->getPathColumnValue())),
                $this->getParentColumnName() => $newParent->getKey()
            ]);
        }

        return $childQuery->where($this->getPathColumnName(), 'like', $this->getPathColumnValue('%'))->delete();
    }

    /**
     * restore category
     */
    public function restoreCateAndChild()
    {
        $childQuery = $this->newQuery();
        $childQuery->withTrashed()->where($this->getPathColumnName(), 'like', $this->getPathColumnValue('%'))->restore();
    }

    /**
     * Check is root
     * @return bool
     */
    public function isRoot()
    {
        return is_null($this->getParentColumnValue());
    }

    /**
     * Check isn't root
     * @return bool
     */
    public function isChild()
    {
        return !is_null($this->getParentColumnValue());
    }

    /**
     * Check is leaf
     *
     * @return bool
     */
    public function isLeaf()
    {
        if (!isset($this->relations['children'])) {
            $this->load('children');
        }

        return $this->children->count() === 0;
    }

    /**
     * Check this is child of destination category
     * @param $category
     * @return bool
     */
    public function isChildOf($category)
    {
        if (($category instanceof $this) === false) {
            $category = $this->newQuery()->find($category);
            if (is_null($category)) {
                return false;
            }
        }

        return $this->getParentColumnValue() === $category->getKey();
    }

    /**
     * Check this is descendant of destination category
     * @param $category
     * @param bool $orSelf
     * @return bool
     */
    public function isDescendantOf($category, $orSelf = false)
    {
        if (($category instanceof $this) === false) {
            $category = $this->newQuery()->find($category);
        }
        if ($orSelf === true && $category->getKey() === $this->getKey()) {
            return true;
        }

        return strpos($this->getPathColumnValue(), $category->getPathColumnValue()) === 0;
    }

    /**
     * Check this is self or descendant of destination category
     * @param $category
     * @return bool
     */
    public function isSelfOrDescendantOf($category)
    {
        return $this->isDescendantOf($category, true);
    }

    /**
     * Check this is ancestor of destination category
     * @param $category
     * @param bool $orSelf
     * @return bool
     */
    public function isAncestorOf($category, $orSelf = false)
    {
        if (($category instanceof $this) === false) {
            $category = $this->newQuery()->find($category);
        }
        if ($orSelf === true && $category->getKey() === $this->getKey()) {
            return true;
        }

        if (empty($this->getPathColumnValue())) {
            return false;
        }

        return strpos($category->getPathColumnValue(), $this->getPathColumnValue()) === 0;
    }

    /**
     * Check this is self or ancestor of destination category
     * @param $category
     * @return bool
     */
    public function isSelfOrAncestorOf($category)
    {
        return $this->isAncestorOf($category, true);
    }

    /**
     * @param Builder $q
     * @return Collection
     */
    public function scopeRoots($q)
    {
        return $q->whereNull($this->getParentColumnName());
    }

    /**
     * @param Builder $q
     * @return Collection
     */
    public function scopeAllLeaves($q)
    {
        return $q->whereNotIn($this->getKeyName(), function ($query) {
            $parentColumn = $this->getParentColumnName();

            return $query->from($this->getTable())->whereNotNull($parentColumn)->groupBy($parentColumn)->select($parentColumn);
        });
    }

    /**
     * @param Builder $q
     * @return Collection
     */
    public function scopeWithoutRoot($q)
    {
        return $q->whereNotNull($this->getParentColumnName());
    }

    /**
     * Get level of current category
     * @return int
     */
    public function getLevel()
    {
        return substr_count($this->getPathColumnValue(), '-');
    }


    /**
     * Query list ancestors
     * @param bool $andSelf
     * @return bool|\Illuminate\Database\Eloquent\Builder|static
     */
    public function ancestors($andSelf = false)
    {
        if ($this->isRoot()) {
            return false;
        }
        $path = $this->parsePath();
        if (!$path) {
            return false;
        }
        if (!$andSelf) {
            array_pop($path);
        }
        $query = $this->newQuery();
        $count = count($path);
        for ($i = 1; $i <= $count; $i++) {
            $query = $query->orWhere($this->getPathColumnName(), '=', implode('', array_slice($path, 0, $i)));
        }

        return $query;
    }

    public function ancestorsAndSelf()
    {
        return $this->ancestors(true);
    }

    public function siblings($andSelf = false)
    {
        $query = $this->newQuery();
        if (!$andSelf) {
            $query = $query->where($this->getKeyName(), '<>', $this->getKey());
        }

        return $query->where($this->getParentColumnName(), '=', $this->getParentColumnValue());
    }

    public function siblingsAndSelf()
    {
        return $this->siblings(true);
    }

    public function descendants($depth = false, $andSelf = false)
    {
        $query = $this->newQuery();
        if ($andSelf === false) {
            $query = $query->where($this->getKeyName(), '<>', $this->getKey());
        }
        $query = $query->where($this->getPathColumnName(), 'like', $this->getPathColumnValue('%'));
        if ($depth !== false) {
            $query = $query->limitDepth($depth);
        }

        return $query;
    }


    public function descendantsAndSelf($depth = false)
    {
        return $this->descendants($depth, true);
    }

    public function scopeLimitDepth($query, $depth)
    {
        return $query->where(
            \DB::raw(
                sprintf('(LENGTH(%s) - LENGTH(REPLACE(%s, "-", "")))', $this->getParentColumnName(), $this->getParentColumnName())
            ),
            '>=',
            $this->getLevel() + $depth
        );
    }

    public function root()
    {
        if ($this->isRoot()) {
            return false;
        }
        $query = $this->newQuery();
        $path = $this->parsePath();
        if ($path === false) {
            return false;
        }

        return $query->where($this->getPathColumnName(), '=', $path[0]);
    }

    public function getRoot($attributes = ['*'])
    {
        return $this->root()->first($attributes);
    }

    public function getDescendantsAndSelf($depth = false, $attributes = ['*'])
    {
        return $this->descendantsAndSelf($depth)->get($attributes);
    }

    public function getDescendants($depth = false, $attributes = ['*'])
    {
        return $this->descendants($depth)->get($attributes);
    }

    public function getSiblingsAndSelf($attributes = ['*'])
    {
        return $this->siblingsAndSelf()->get($attributes);
    }

    public function getSiblings($attributes = ['*'])
    {
        return $this->siblings()->get($attributes);
    }

    public function getAncestorsAndSelf($attributes = ['*'])
    {
        return $this->ancestorsAndSelf()->get($attributes);
    }

    public function getAncestors($attributes = ['*'])
    {
        return $this->ancestors()->get($attributes);
    }

    public function scopeRebuild($query)
    {
        $parentIds = $query->groupBy($this->getParentColumnName())->lists($this->getParentColumnName());
        foreach ($parentIds as $parentId) {
            $parent = $this->find($parentId);
            if ($parent) {
                $parentPath = $parent->getPathColumnValue();
            } else {
                $parentPath = '';
            }
            $this->where($this->getParentColumnName(), $parentId)
                ->update([
                    $this->getPathColumnName() => \DB::raw(sprintf('CONCAT("%s", %s, "-")', $parentPath, $this->getKeyName()))
                ]);
        }

        return true;
    }

    public function allWithout($category, $withoutChildren = false)
    {
        if (is_numeric($category)) {
            $category = $this->newQuery()->find($category);
            if (!$category) {
                return false;
            }
        }
        if ($withoutChildren) {
            return $this->newQuery()->where(
                $category->getPathColumnName(),
                'not like',
                $category->getPathColumnValue($withoutChildren ? '%' : '')
            );
        }

        return $this->newQuery()->where(
            $category->getPathColumnName(),
            '<>',
            $category->getPathColumnValue()
        );

    }

    public function allWithoutAndChildrend($category)
    {
        return $this->allWithout($category, true);
    }

    public function allWithoutSelf()
    {
        return $this->allWithout($this);
    }

    public function allWithoutSelfAndChildren()
    {
        return $this->allWithout($this, true);
    }

    public static function buildTree($data, $parent_id = null)
    {
        foreach ($data as $key => $category) {
            $query = new static;
            $query->name = $category['name'];
            $query->slug = !isset($category['slug']) ? $query->generateSlug($category['name']) : $category['slug'];
            $query->position = $key;
            $query->{$query->getParentColumnName()} = $parent_id;
            $query->save();
            if (isset($category['children'])) {
                $query->buildTree($category['children'], $query->getKey());
            }
        }
    }

    /**
     * @param array $data
     * @return int
     */
    public function updateNestedMenu(array $data)
    {
        $ids = [];
        $idWhereIn = [];
        $updateData = [];
        $position = [];
        $sql = sprintf('UPDATE `%s` SET `%s` = CASE', $this->getTable(), $this->getParentColumnName());

        foreach ($data as $key => $item) {
            $updateData[] = $item['id'];
            $updateData[] = $item['parent_id'];
            $ids[] = $item['id'];
            $position[] = $item['id'];
            $position[] = $key;
            $idWhereIn[] = '?';
        }
        $updateData = array_merge($updateData, $position, $ids);
        $sql .= $this->updateColumnWithClause($data);
        $sql .= ', `position` = CASE';
        $sql .= $this->updateColumnWithClause($data);
        $sql .= sprintf(' WHERE %s IN (%s)', $this->getKeyName(), implode(',', $idWhereIn));

        return \DB::update(\DB::raw($sql), $updateData);
    }

    /**
     * update multi record
     * @param  array $data
     * @param  string $field column
     * @return string        sql
     */
    private function updateColumnWithClause($data)
    {
        $sql = '';
        foreach ($data as $item)
            $sql .= sprintf(' WHEN %s = ? THEN ?', $this->getKeyName());
        $sql .= ' END';

        return $sql;
    }

    protected function parsePath()
    {
        preg_match_all('/[0-9]+\-/', $this->getPathColumnValue(), $path);
        $path = reset($path);
        if (empty($path[0])) {
            return false;
        }

        return $path;
    }
}
