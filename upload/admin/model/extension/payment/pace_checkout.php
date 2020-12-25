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
    }

    public function uninstall()
    {
        $this->db->query("
        DROP TABLE IF EXISTS " . DB_PREFIX . "order_transaction");
    }
}
