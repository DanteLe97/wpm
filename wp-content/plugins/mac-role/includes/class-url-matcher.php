<?php
/**
 * URL Matcher - Check if URL matches allowed patterns and related URLs
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RUD_URL_Matcher {
	
	/**
	 * Get related URLs for a given admin URL
	 * Example: edit.php?post_type=page -> also allows post-new.php?post_type=page, post.php?post=*&action=edit
	 */
	public static function get_related_urls( $url ) {
		$related = array( $url ); // Always include the original URL
		
		$normalized = RUD_Helpers::normalize_url( $url );
		
		// Parse URL
		$parsed = parse_url( $normalized );
		$path = isset( $parsed['path'] ) ? $parsed['path'] : '';
		$query = isset( $parsed['query'] ) ? $parsed['query'] : '';
		parse_str( $query, $params );
		
		// Handle edit.php - allows list, add new, and edit specific posts
		if ( $path === 'edit.php' || strpos( $normalized, 'edit.php' ) !== false ) {
			// Add post-new.php with same parameters
			if ( isset( $params['post_type'] ) ) {
				$related[] = 'post-new.php?post_type=' . $params['post_type'];
			} else {
				$related[] = 'post-new.php';
			}
			
			// Add post.php pattern (for editing specific posts)
			if ( isset( $params['post_type'] ) ) {
				$related[] = 'post.php?post_type=' . $params['post_type'] . '&action=edit';
				$related[] = 'post.php?post_type=' . $params['post_type'];
			} else {
				$related[] = 'post.php?action=edit';
				$related[] = 'post.php';
			}
		}
		
		// Handle post-new.php - allows list and edit
		if ( $path === 'post-new.php' || strpos( $normalized, 'post-new.php' ) !== false ) {
			if ( isset( $params['post_type'] ) ) {
				$related[] = 'edit.php?post_type=' . $params['post_type'];
				$related[] = 'post.php?post_type=' . $params['post_type'] . '&action=edit';
			} else {
				$related[] = 'edit.php';
				$related[] = 'post.php?action=edit';
			}
		}
		
		// Handle post.php - allows list and add new
		if ( $path === 'post.php' || strpos( $normalized, 'post.php' ) !== false ) {
			if ( isset( $params['post_type'] ) ) {
				$related[] = 'edit.php?post_type=' . $params['post_type'];
				$related[] = 'post-new.php?post_type=' . $params['post_type'];
			} else {
				$related[] = 'edit.php';
				$related[] = 'post-new.php';
			}
		}
		
		// For admin.php?page= URLs, don't generate related URLs (they're standalone)
		if ( strpos( $normalized, 'admin.php?page=' ) !== false ) {
			return array( $url ); // Just return the original URL
		}
		
		// Normalize all related URLs
		$related = array_map( function( $url ) {
			return RUD_Helpers::normalize_url( $url );
		}, $related );
		
		return array_unique( $related );
	}
	
	/**
	 * Check if current URL matches any of the allowed URLs (including related)
	 */
	public static function is_url_allowed( $current_url, $allowed_urls ) {
		$normalized_current = RUD_Helpers::normalize_url( $current_url );
		
		// Extract page parameter from current URL for admin.php?page= URLs
		$current_page = '';
		if ( strpos( $normalized_current, 'admin.php?page=' ) !== false ) {
			$current_query = parse_url( $normalized_current, PHP_URL_QUERY );
			if ( $current_query ) {
				parse_str( $current_query, $current_params );
				$current_page = isset( $current_params['page'] ) ? $current_params['page'] : '';
			}
		}
		
		foreach ( $allowed_urls as $allowed_url ) {
			$normalized_allowed = RUD_Helpers::normalize_url( $allowed_url );
			
			// Check exact match first
			if ( $normalized_current === $normalized_allowed ) {
				return true;
			}
			
			// Special handling for admin.php?page= URLs
			if ( ! empty( $current_page ) && strpos( $normalized_allowed, 'admin.php?page=' ) !== false ) {
				$allowed_query = parse_url( $normalized_allowed, PHP_URL_QUERY );
				if ( $allowed_query ) {
					parse_str( $allowed_query, $allowed_params );
					$allowed_page = isset( $allowed_params['page'] ) ? $allowed_params['page'] : '';
					
					// Match by page parameter
					if ( $current_page === $allowed_page && ! empty( $current_page ) ) {
						return true;
					}
				}
			}
			
			// Get all related URLs for this allowed URL
			$related_urls = self::get_related_urls( $allowed_url );
			
			// Check against related URLs
			foreach ( $related_urls as $related_url ) {
				$normalized_related = RUD_Helpers::normalize_url( $related_url );
				
				// Exact match
				if ( $normalized_current === $normalized_related ) {
					return true;
				}
				
				// Special handling for admin.php?page= URLs in related URLs
				if ( ! empty( $current_page ) && strpos( $normalized_related, 'admin.php?page=' ) !== false ) {
					$related_query = parse_url( $normalized_related, PHP_URL_QUERY );
					if ( $related_query ) {
						parse_str( $related_query, $related_params );
						$related_page = isset( $related_params['page'] ) ? $related_params['page'] : '';
						
						if ( $current_page === $related_page && ! empty( $current_page ) ) {
							return true;
						}
					}
				}
				
				// Pattern match for post.php with dynamic post ID
				if ( strpos( $normalized_related, 'post.php' ) !== false && 
					 strpos( $normalized_current, 'post.php' ) !== false ) {
					
					// Extract post_type from both URLs
					$current_params = array();
					$related_params = array();
					
					$current_query = parse_url( $normalized_current, PHP_URL_QUERY );
					$related_query = parse_url( $normalized_related, PHP_URL_QUERY );
					
					if ( $current_query ) {
						parse_str( $current_query, $current_params );
					}
					if ( $related_query ) {
						parse_str( $related_query, $related_params );
					}
					
					// If post_type matches, allow
					$current_post_type = isset( $current_params['post_type'] ) ? $current_params['post_type'] : 'post';
					$related_post_type = isset( $related_params['post_type'] ) ? $related_params['post_type'] : 'post';
					
					if ( $current_post_type === $related_post_type ) {
						return true;
					}
				}
				
				// Pattern match for edit.php - allow any post_type if base URL matches
				if ( strpos( $normalized_related, 'edit.php' ) !== false && 
					 strpos( $normalized_current, 'edit.php' ) !== false ) {
					
					$current_query = parse_url( $normalized_current, PHP_URL_QUERY );
					$related_query = parse_url( $normalized_related, PHP_URL_QUERY );
					
					$current_params = array();
					$related_params = array();
					
					if ( $current_query ) {
						parse_str( $current_query, $current_params );
					}
					if ( $related_query ) {
						parse_str( $related_query, $related_params );
					}
					
					$current_post_type = isset( $current_params['post_type'] ) ? $current_params['post_type'] : 'post';
					$related_post_type = isset( $related_params['post_type'] ) ? $related_params['post_type'] : 'post';
					
					if ( $current_post_type === $related_post_type ) {
						return true;
					}
				}
				
				// Pattern match for post-new.php
				if ( strpos( $normalized_related, 'post-new.php' ) !== false && 
					 strpos( $normalized_current, 'post-new.php' ) !== false ) {
					
					$current_query = parse_url( $normalized_current, PHP_URL_QUERY );
					$related_query = parse_url( $normalized_related, PHP_URL_QUERY );
					
					$current_params = array();
					$related_params = array();
					
					if ( $current_query ) {
						parse_str( $current_query, $current_params );
					}
					if ( $related_query ) {
						parse_str( $related_query, $related_params );
					}
					
					$current_post_type = isset( $current_params['post_type'] ) ? $current_params['post_type'] : 'post';
					$related_post_type = isset( $related_params['post_type'] ) ? $related_params['post_type'] : 'post';
					
					if ( $current_post_type === $related_post_type ) {
						return true;
					}
				}
			}
		}
		
		return false;
	}
	
	/**
	 * Extract post_type from URL
	 */
	public static function extract_post_type( $url ) {
		$normalized = RUD_Helpers::normalize_url( $url );
		$query = parse_url( $normalized, PHP_URL_QUERY );
		
		if ( $query ) {
			parse_str( $query, $params );
			return isset( $params['post_type'] ) ? $params['post_type'] : null;
		}
		
		return null;
	}
}

