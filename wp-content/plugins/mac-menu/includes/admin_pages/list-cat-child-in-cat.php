<?php
    $objmacMenu = new macMenu();
    $result = $objmacMenu->all_cat_by_parent_cat_menu($id_category);
?>

<div class="inside">
    <div class="mac-list-cat-child sortable">
    <?php foreach( $result as $item ): ?>
        <div class="list-item">
            <?php 
            $catChildConfigs = array();
            $catChildConfigs['id_category'] = $item->id;
            echo '<input class="btn-delete-cat-menu" type="button" value="Delete">';
            echo '<a class="btn-edit-cat-menu" href="admin.php?page=mac-cat-menu&id='.$item->id.'">Edit</a>';
            echo '<div class="mac-list-heading mac-list-cat-child__heading mac-collapsible mac-collapsible-btn">';
            echo '<h4 class="mac-heading-title">'.$item->category_name.'</h4>';
            echo '<div class="mac-heading-button"><span>+</span><span>-</span></div>';
            echo '</div>';
            echo $renderCatHTML->render($catChildConfigs);  
            
            $allChildMenu = $objmacMenu->all_cat_by_parent_cat_menu($item->id);
            if($allChildMenu) {
                echo '<table class="form-table-child">';
                echo '<tbody>';
                echo '<tr>';
                    echo '<td>List Category Child Item</td>';
                    echo '<td>';
                        echo '<div class="mac-list-cat-child">';
                        foreach($allChildMenu as $itemCatChild):
                            echo '<div class="list-child-item">';
                            echo '<div class="mac-list-heading">';
                            echo '<h4 class="mac-heading-title"><a href="admin.php?page=mac-cat-menu&id='.$itemCatChild->id.'">'.$itemCatChild->category_name.'</a></h4>';
                            echo '</div>';
                            echo '</div>';
                        endforeach;
                        echo '</div>';
                    echo '</td>';
                echo '</tr>';
                echo '</tbody>';
                echo '</table>';
                
            }
            ?>
        </div>
    <?php endforeach;?>
    </div>
    <div class="overlay" id="overlay"></div>
    <div class="confirm-dialog" id="confirmDialog" >
        <p>Are you sure you want to delete?</p>
        <input name="formIdDeledte" value="" readonly style="opacity: 0; visibility: hidden;"/>
        <div class="btn-wrap">
            <div id="confirmOk">OK</div>
            <div id="confirmCancel">Cancel</div>
        </div>
        
    </div>

</div>