<?php

use Create_product_book as GlobalCreate_product_book;

/**
 *
 */

class Datas_Metasbooks
{
  // api's url (function lookup_metas)


  public static function create ( $apikey, $ean, $stock) {

	global $wpdb;

    $json = self::get_metas ($apikey, $ean);

    if (isset($json->code_reponse) && $json->code_reponse == 0)
	{
		return false;
	}
    else 
    {
        Create_product_book::create($json, $stock);
        return true;
    }
	

  }

  public static function get_metas ( $apikey, $ean) {
    
    $json  = 'https://metasbooks.fr/api/lookup_metas.php?apikey='.$apikey.'&ean='.$ean.'&format=json';
    $json = file_get_contents($json);
    return json_decode($json);

  }
}

class Create_product_book
{

    public static function create($json, $stock)
    {

        $datas = $json;
		$post = array(
			'post_author' 	=> 'MetasBooks',
			'post_excerpt' 	=> $datas->resume_final,
			'post_status' 	=> "publish",
			'post_title' 	=> $datas->titre,
			'post_type' 	=> "product",
			'post_content' 	=> $datas->resume_final
		);

		//Create post
		$post_id = wp_insert_post( $post );
		if($post_id){
			$attach_id = get_post_meta($post_id, "_thumbnail_id", true);
			add_post_meta($post_id, '_thumbnail_id', $attach_id);
		}
		wp_set_object_terms($post_id, 'simple', 'product_type');

		update_post_meta( $post_id, '_visibility', 'visible' );
		update_post_meta( $post_id, '_stock_status', 'instock');
		update_post_meta( $post_id, 'total_sales', '0');
		update_post_meta( $post_id, '_downloadable', 'no');
		update_post_meta( $post_id, '_virtual', 'no');
		update_post_meta( $post_id, '_regular_price', $datas->prix);
		update_post_meta( $post_id, '_sale_price', $datas->prix );
		update_post_meta( $post_id, '_purchase_note', "" );
		update_post_meta( $post_id, '_featured', "no" );
		update_post_meta( $post_id, '_weight', $datas->poids/1000 );
		update_post_meta( $post_id, '_length', "" );
		update_post_meta( $post_id, '_sku', $datas->ean );
		update_post_meta( $post_id, '_product_attributes', array());
		update_post_meta( $post_id, '_sale_price_dates_from', "" );
		update_post_meta( $post_id, '_sale_price_dates_to', "" );
		update_post_meta( $post_id, '_price', $datas->prix );
		update_post_meta( $post_id, '_sold_individually', "" );
		update_post_meta( $post_id, '_manage_stock', "yes" );
		update_post_meta( $post_id, '_backorders', "no" );
		update_post_meta( $post_id, '_stock', $stock );
		update_post_meta( $post_id, '_width', $datas->largeur );
		update_post_meta( $post_id, '_height', $datas->epaisseur);
		update_post_meta($post_id, '_length', $datas->hauteur);
		add_post_meta ($post_id, '_auteur', $datas->auteur);
		add_post_meta($post_id, '_format', $datas->format);
		add_post_meta($post_id, '_editeur', $datas->editeur);
		add_post_meta($post_id, '_collection', $datas->collection);
		add_post_meta($post_id, '_presentation', $datas->presentation);
		add_post_meta($post_id, '_date', $datas->date);
		add_post_meta($post_id, '_nbpages', $datas->nbpages);
		add_post_meta($post_id, '_poids', $datas->poids);
		add_post_meta($post_id, '_auteur', $datas->auteur);

		if (isset($datas->serie))
		{
			add_post_meta($post_id, '_serie', $datas->serie);
			add_post_meta($post_id, '_num_serie', $datas->num_serie);
		}

		$cat_slugs = explode('>', $datas->classification);
		$slug_index = count($cat_slugs);

		if (term_exists('livres') === NULL)
		{
			$cat_arr = array(
				'name' => 'Livres',
				'slug' => 'livres',
				'description' => 'livres'
			);
			wp_insert_term('Livres', 'product_cat', $cat_arr);
		}
		

		for ($i = 0; $i < $slug_index; $i++)
		{
			if ( term_exists($cat_slugs[$i]) === NULL )
			{
				if ($i === 0) $parent = 'livres';

				else $parent = $cat_slugs[$i - 1];

				$parent_term = term_exists($parent);
				$parent_term_id = $parent_term;

				$cat_arr = array(
					'name' => $cat_slugs[$i],
					'slug' => $cat_slugs[$i],
					'description' => $cat_slugs[$i],
					'parent' => $parent_term_id
				);
				wp_insert_term($cat_slugs[$i], 'product_cat', $cat_arr);
			}			
		}
		wp_set_object_terms($post_id, $cat_slugs[$slug_index - 1], 'product_cat');

		//image
		if (!empty($datas->image_url)) self::add_product_thumbnail_beta($post_id, $datas->image_url);
							
    }

	public static function add_product_thumbnail_beta ($post_id, $url) 
	
	{
		// Add Featured Image to Post
		$image_url        = $url; // Define the image URL here
		$exploded		  = explode('/', $image_url);
		$count_exploded	  = count($exploded);
		$image_name      = $exploded[$count_exploded -1];
		$upload_dir       = wp_upload_dir(); // Set upload folder
		$image_data       = file_get_contents($image_url); // Get image data
		$unique_file_name = wp_unique_filename($upload_dir['path'], $image_name); // Generate unique name
		$filename         = basename($unique_file_name); // Create image file name

		// Check folder permission and define file location
		if (wp_mkdir_p($upload_dir['path'])) {
			$file = $upload_dir['path'] . '/' . $filename;
		} else {
			$file = $upload_dir['basedir'] . '/' . $filename;
		}

		// Create the image  file on the server
		file_put_contents($file, $image_data);

		// Check image file type
		$wp_filetype = wp_check_filetype($filename, null);

		// Set attachment data
		$attachment = array(
			'post_mime_type' => $wp_filetype['type'],
			'post_title'     => sanitize_file_name($filename),
			'post_content'   => '',
			'post_status'    => 'inherit'
		);

		// Create the attachment
		$attach_id = wp_insert_attachment($attachment, $file, $post_id);

		// Include image.php
		require_once(ABSPATH . 'wp-admin/includes/image.php');

		// Define attachment metadata
		$attach_data = wp_generate_attachment_metadata($attach_id, $file);

		// Assign metadata to attachment
		wp_update_attachment_metadata($attach_id, $attach_data);

		// And finally assign featured image to post
		set_post_thumbnail($post_id, $attach_id);
	}
}
