<?php

/**
 * Render html for plugin https://github.com/dbushell/Nestable
 * @param $node
 * @return string
 */
function renderNode($node)
{
    if ($node->isLeaf()) {
        return sprintf('<li class="dd-item" data-id="%s">
            <div class="dd-handle dd3-handle">
                Drag
            </div>
            <div class="dd3-content">
                <div class="pull-left">%s</div>
                <div class="pull-right item-action" data-id="%s">
                    <a class="edit-item" data-toggle="modal" href="#baseModal"><i class="fa fa-pencil-square-o"></i></a>
                    <a class="remove-item" data-toggle="modal" href="#baseModal"><i class="fa fa-trash"></i></a>
                </div>
                </div>
            </li>', $node->getKey(), $node->name, $node->getKey());
    } else {
        $html = sprintf('<li class="dd-item" data-id="%s">
            <div class="dd-handle dd3-handle">
                Drag
            </div>
            <div class="dd3-content">
                <div class="pull-left">%s</div>
                <div class="pull-right item-action" data-id="%s">
                    <a class="edit-item" data-toggle="modal" href="#baseModal"><i class="fa fa-pencil-square-o"></i></a>
                    <a class="remove-item" data-toggle="modal" href="#baseModal"><i class="fa fa-trash"></i></a>
                </div>
            </div>', $node->getKey(), $node->name, $node->getKey());
        $html .= '<ol class="dd-list">';
        foreach ($node->children()->orderBy('parent_id')->orderBy('position')->get() as $child) {
            $html .= renderNode($child);
        }
        $html .= '</ol>';
        $html .= '</li>';

        return $html;
    }

}

if (!function_exists('getCategoryArrayId')) {
    /**
     * Simple input data:
     * $array = [
     *       ['id' => '1'],
     *       ['id' => '2'],
     *       ['id' => '3', 'children' => [
     *           ['id' => '4', 'children' => [
     *               ['id' => '5'],
     *               ['id' => '6']
     *           ],
     *           ],
     *           ['id' => '7', 'children' => [
     *               ['id' => '8'],
     *               ['id' => '9'],
     *               ['id' => '10'],
     *               ['id' => '11']
     *           ],
     *           ],
     *           ['id' => '12']
     *       ],
     *       ],
     *       ['id' => '13']
     *   ];
     * Simple output
     * $newData = [
     *       ['id' => '1', 'parent_id' => null],
     *       ['id' => '2', 'parent_id' => null],
     *       ['id' => '3', 'parent_id' => null],
     *       ['id' => '4', 'parent_id' => '3'],
     *       ['id' => '5', 'parent_id' => '4'],
     *       ['id' => '6', 'parent_id' => '4'],
     *       ['id' => '7', 'parent_id' => '3'],
     *       ['id' => '8', 'parent_id' => '7'],
     *       ['id' => '9', 'parent_id' => '7'],
     *       ['id' => '10', 'parent_id' => '7'],
     *       ['id' => '11', 'parent_id' => '7'],
     *       ['id' => '12', 'parent_id' => '3'],
     *       ['id' => '13', 'parent_id' => null]
     *   ];
     * @param $array
     * @param null $parent_id
     * @return array
     */
    function getCategoryArrayId($array, $parent_id = null)
    {
        $newData = [];
        foreach ($array as $item) {
            $newData[] = [
                'id' => $item['id'],
                'parent_id' => $parent_id
            ];
            if (isset($item['children'])) {
                $newData = array_merge($newData, getCategoryArrayId($item['children'], $item['id']));
            }
        }

        return $newData;

    }
}
