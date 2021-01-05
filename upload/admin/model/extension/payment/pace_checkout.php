<?php

class ModelExtensionPaymentPaceCheckout extends Controller
{
    public function install()
    {
        $this->db->query("
			CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "order_transaction` (
				`id` INT(11) NOT NULL AUTO_INCREMENT,
				`order_id` int(11) NOT NULL,
				`transaction_id` varchar(255) NOT NULL,
				`data` text NOT NULL,
				PRIMARY KEY `id` (`id`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
        ");
        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "cron` (
            `cron_id` int(11) NOT NULL AUTO_INCREMENT,
            `status` int(6) NOT NULL DEFAULT '0',
            `created_at` datetime NOT NULL,
            `updated_at` datetime NOT NULL,
            PRIMARY KEY (`cron_id`)
          ) ENGINE=MyISAM DEFAULT CHARSET=utf8;");
        $this->db->query("TRUNCATE TABLE `oc_cron`");
        $this->db->query("INSERT INTO `oc_cron` (`cron_id`, `created_at`, `updated_at`)
        VALUES
            (1, UTC_TIMESTAMP(), UTC_TIMESTAMP());
        ");
    }

    public function uninstall()
    {
        $this->db->query("
        DROP TABLE IF EXISTS " . DB_PREFIX . "order_transaction");

        $this->db->query("
        DROP TABLE IF EXISTS " . DB_PREFIX . "cron");
    }
}
