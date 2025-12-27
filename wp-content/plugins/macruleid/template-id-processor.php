<?php
/**
 * Template ID Processor
 * Xử lý logic parse template IDs và merge containers từ nhiều sources
 * 
 * PORTABLE: Plugin này sử dụng WordPress functions (get_post_meta) 
 * thay vì direct database connection, có thể chuyển sang bất kỳ website nào
 */

class Template_ID_Processor {
    

    /**
     * Parse input string và trả về merged containers JSON
     * 
     * @param string $template_ids_string Ví dụ: "111,page:222-tem:e275946"
     * @return string JSON array của merged containers
     */
    public function process_template_ids($template_ids_string) {
        $containers_to_merge = array();
        $template_ids = explode(',', $template_ids_string);
        
        foreach ($template_ids as $template_id) {
            $template_id = trim($template_id);
            
            if (empty($template_id)) {
                continue;
            }
            
            if (strpos($template_id, 'page:') === 0) {
                // Extract container from specific page
                $parts = explode('-tem:', $template_id);
                if (count($parts) === 2) {
                    $page_id = str_replace('page:', '', $parts[0]);
                    $container_id = $parts[1];
                    $container = $this->get_container_from_page($page_id, $container_id);
                    if ($container) {
                        $containers_to_merge[] = $container;
                    }
                }
            } else {
                // Get all containers from page
                $containers = $this->get_containers_from_page($template_id);
                if (!empty($containers)) {
                    $containers_to_merge = array_merge($containers_to_merge, $containers);
                }
            }
        }
        
        return json_encode($containers_to_merge);
    }
    


    /**
     * Lấy container cụ thể từ một page
     * 
     * @param int $page_id ID của page
     * @param string $container_id ID của container cần lấy
     * @return array|null Container data hoặc null nếu không tìm thấy
     */
    private function get_container_from_page($page_id, $container_id) {
        $elementor_data = get_post_meta($page_id, '_elementor_data', true);
        if (empty($elementor_data)) {
            return null;
        }

        $containers = json_decode($elementor_data, true);
        if (!is_array($containers)) {
            return null;
        }

        return $this->find_container_by_id($containers, $container_id);
    }

    /**
     * Lấy tất cả containers từ một page
     * 
     * @param int $page_id ID của page
     * @return array Array các containers
     */
    private function get_containers_from_page($page_id) {
        $elementor_data = get_post_meta($page_id, '_elementor_data', true);
        if (empty($elementor_data)) {
            return array();
        }

        $containers = json_decode($elementor_data, true);
        if (!is_array($containers)) {
            return array();
        }

        return $containers;
    }

    /**
     * Tìm container theo ID trong array containers
     * 
     * @param array $containers Array các containers để tìm
     * @param string $container_id ID của container cần tìm
     * @return array|null Container data hoặc null nếu không tìm thấy
     */
    private function find_container_by_id($containers, $container_id) {
        foreach ($containers as $container) {
            if (isset($container['id']) && $container['id'] === $container_id) {
                return $container;
            }
            
            // Tìm trong các elements con
            if (isset($container['elements']) && is_array($container['elements'])) {
                $found = $this->find_container_by_id($container['elements'], $container_id);
                if ($found) {
                    return $found;
                }
            }
        }
        
        return null;
    }
}

