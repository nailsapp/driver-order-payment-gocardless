<?php

/**
 * Migration:   0
 * Started:     25/01/2016
 * Finalised:
 *
 * @package     Nails
 * @subpackage  driver-invoice-gocardless
 * @category    Database Migration
 * @author      Nails Dev Team
 * @link
 */

namespace Nails\Database\Migration\Nails\DriverInvoiceGocardless;

use Nails\Common\Console\Migrate\Base;

class Migration0 extends Base
{
    /**
     * Execute the migration
     * @return Void
     */
    public function execute()
    {
        $this->query("
            CREATE TABLE `{{NAILS_DB_PREFIX}}user_meta_invoice_gocardless_mandate` (
                `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                `user_id` int(11) unsigned NOT NULL,
                `label` varchar(150) NOT NULL DEFAULT '',
                `mandate_id` varchar(50) NOT NULL DEFAULT '',
                `created` datetime NOT NULL,
                PRIMARY KEY (`id`),
                KEY `user_id` (`user_id`),
                CONSTRAINT `{{NAILS_DB_PREFIX}}user_meta_invoice_gocardless_mandate_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `{{NAILS_DB_PREFIX}}user` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
        ");

        //  @todo (Pablo - 2019-09-05) - create a new migration to remove this table (the only known project relying on it will/has handled migrating it)
        //  @todo (Pablo - 2019-09-05) - Sorry if this screws anyone else over :( pre-release, innit.
    }
}
