<?php
/* Copyright (C) 2015		Jean-François Ferry		<jfefe@aternatik.fr>
 * Copyright (C) 2024		Frédéric France			<frederic.france@free.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

use Luracast\Restler\RestException;

/**
 * \file    htdocs/takepos/class/api_takepos.class.php
 * \ingroup takepos
 * \brief   File for API management of TakePOS.
 */

/**
 * API class for TakePOS
 *
 * @access protected
 * @class  DolibarrApiAccess {@requires user,external}
 */
class TakePos extends DolibarrApi
{
    /**
     * Constructor
     *
     * @url     GET /
     */
    public function __construct()
    {
        global $db;
        $this->db = $db;
    }

    /**
     * List TakePOS tables (floors)
     *
     * Return an array of tables for a floor, or all tables if floor is not specified.
     * Optionally return only unused tables.
     *
     * @param int|null $floor Floor number (optional, default null for all floors)
     * @param int $onlyunused If 1, return only tables not occupied (default 0)
     * @param int|null $terminal Terminal id to look for provisional invoices. If null we try session value
     * @return array<int,array<string,mixed>> Array of rows from takepos_floor_tables with an extra 'occupied' boolean
     *
     * @url GET /tables
     *
     * @throws RestException 403 Not allowed
     * @throws RestException 503 System error
     */
    public function tables($floor = null, $onlyunused = 0, $terminal = null)
    {
        if (!DolibarrApiAccess::$user->hasRight('takepos', 'run')) {
            throw new RestException(403);
        }

        if ($floor !== null) {
            $floor = (int) $floor;
        }
        $onlyunused = (int) $onlyunused;

        // Decide terminal id: prefer provided, else session, else 1
        if ($terminal === null) {
            $terminal = isset($_SESSION['takeposterminal']) ? $_SESSION['takeposterminal'] : 1;
        }

        require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';

        $rows = array();
        $sql = "SELECT rowid, entity, label, leftpos, toppos, floor";
        $sql .= " FROM ".MAIN_DB_PREFIX."takepos_floor_tables";
        $sql .= " WHERE 1=1";
        if ($floor !== null) {
            $sql .= " AND floor = ".((int) $floor);
        }
        $sql .= " AND entity IN (".getEntity('takepos').")";

        $resql = $this->db->query($sql);
        if ($resql) {
            while ($row = $this->db->fetch_array($resql)) {
                // Keep only associative keys (filter out numeric indices from fetch_array())
                $row = array_filter($row, 'is_string', ARRAY_FILTER_USE_KEY);
                
                $tmpplace = (int) $row['rowid'];

                $invoice = new Facture($this->db);
                $result = $invoice->fetch(0, '(PROV-POS'.(int) $terminal.'-'.$tmpplace.')');
                $row['occupied'] = ($result > 0 ? true : false);

                if ($onlyunused && $row['occupied']) {
                    continue;
                }

                $rows[] = $row;
            }
        } else {
            throw new RestException(503, 'Error when retrieving takepos tables: '.$this->db->lasterror());
        }

        return $rows;
    }
}
