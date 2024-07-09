<?php

 if ( ! defined('ABSPATH') ) {
    exit; // Exit if accessed directly
 }

/**
 * Faspay Debit Plugin Installation process
 *
 * This file is used for creating tables while installing the plugins.
 *
 * Copyright (c) faspay
 *
 * This script is only free to the use for merchants of faspay. If
 * you have found this script useful a small recommendation as well as a
 * comment on merchant form would be greatly appreciated.
 *
 * @package     Faspay
 * @author      Periyanto
 *
 */

 /**
  * Creates Faspay tables while activating the plugins
  * Calls from the hook "register_activation_hook"
  *
  * @param none
  * @return void
  */

 function faspay_activation_process(){

        //GetTheTableNameWithTheWPdatabasePrefix
        global $wpdb;
        $table_name = $wpdb->prefix . "faspay_order";
        $table_name2= $wpdb->prefix . "faspay_postdata";
        $table_name3= $wpdb->prefix . "faspay_post";
            
        global $divebook_db_table_dive_version;
        $installed_ver = get_option( "divebook_db_table_dive_version" );

            //Check if the table already exists and if the table is up to date, if not create it
            // if(($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name && $wpdb->get_var("SHOW TABLES LIKE '$table_name2'") != $table_name2)    ||  $installed_ver != $divebook_db_table_dive_version ) {
                $sql = "
                    DROP TABLE IF EXISTS wp_faspay_order;
                    DROP TABLE IF EXISTS wp_faspay_post;
                    DROP TABLE IF EXISTS wp_faspay_postdata;

                    CREATE TABLE " . $table_name . " (
                    id bigint(10) NOT NULL AUTO_INCREMENT,
                    trx_id bigint(255) DEFAULT NULL,
                    trx_id_cc varchar(255) NOT NULL,
                    order_id varchar(30) NOT NULL,
                    date_trx datetime NOT NULL,
                    date_expire datetime NOT NULL,
                    date_payment datetime NOT NULL,
                    total_amount varchar(30) NOT NULL,
                    payment_reff varchar(30) NOT NULL,
                    channel varchar(30) NOT NULL,
                    status varchar(30) NOT NULL,
                    UNIQUE KEY id (id)
                    );

                    CREATE TABLE " . $table_name2 . " (
                    id bigint(10) NOT NULL AUTO_INCREMENT,
                    order_id varchar(30) NOT NULL,
                    date_trx datetime NOT NULL,
                    date_payment datetime NULL,
                    total_amount varchar(30) NOT NULL,
                    post_data longtext NULL,
                    UNIQUE KEY id (id)
                    );

                    CREATE TABLE " . $table_name3 . " (
                    id bigint(10) NOT NULL AUTO_INCREMENT,
                    order_id varchar(30) NOT NULL,
                    date_trx datetime NOT NULL,
                    total_amount varchar(30) NOT NULL,
                    post_data longtext NULL,
                    UNIQUE KEY id (id)
                    );
                    ";
                
                require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
                dbDelta($sql);
                update_option( "divebook_db_table_dive_version", $divebook_db_table_dive_version );
            // }
        
        //AddDatabaseTableVersionsToOptions
        add_option("divebook_db_table_dive_version", $divebook_db_table_dive_version);
        
}
 

 /**
  * Deletes the faspay configuration values from wp_options tables
  * Calls from the hook "register_deactivation_hook"
  *
  * @param none
  * @return void
  */
 function faspay_uninstallation_process() {
    global $wpdb;
    $wpdb->query("  DROP TABLE IF EXISTS wp_faspay_order,
                    DROP TABLE IF EXISTS wp_faspay_post,
                    DROP TABLE IF EXISTS wp_faspay_postdata;");
 }
?>
