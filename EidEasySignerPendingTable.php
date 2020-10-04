<?php
defined('ABSPATH') or die('No script kiddies please!');

class EidEasySignerPendingTable extends WP_List_Table
{
    function get_columns()
    {
        return [
            'cb'         => '<input type="checkbox" />',
            'signer_id'  => 'Signer ID code',
            'user_email' => 'User Email',
            'filename'   => 'Filename',
            'doc_id'     => '',
        ];
    }

    function prepare_items()
    {
        $this->process_bulk_action();
        $columns               = $this->get_columns();
        $hidden                = [];
        $sortable              = [];
        $this->_column_headers = [$columns, $hidden, $sortable];
        $this->items           = get_option('eideasy_pending_provider_signatures', []);
    }

    function column_default($item, $column_name)
    {
        switch ($column_name) {
            case 'doc_id':
                $clientId = get_option('eideasy_client_id');
                $docId    = $item['doc_id'];
                return "<a href=\"https://id.eideasy.com/add-signature?client_id=$clientId&doc_id=$docId\">Add signature</a>";
            case 'signer_id':
            case 'filename':
            case 'user_email':
                return $item[$column_name] ?? '-';
            default:
                return print_r($item, true); //Show the whole array for troubleshooting purposes
        }
    }

    function get_bulk_actions()
    {
        return [
            'delete' => 'Delete',
        ];
    }

    function process_bulk_action()
    {
        if ('delete' === $this->current_action()) {
            foreach ($_POST['doc_id'] as $docId) {
                $docId = sanitize_text_field($docId);
                while (get_option('eideasy_pending_provider_lock')) {
                    usleep(100000);
                    wp_cache_delete('eideasy_pending_provider_lock', 'options');
                }
                update_option('eideasy_pending_provider_lock', true, false);

                $pendingSignatures = get_option('eideasy_pending_provider_signatures', []);
                foreach ($pendingSignatures as $key => $value) {
                    if ($value['doc_id'] === $docId) {
                        unset($pendingSignatures[$key]);
                    }
                }
                update_option('eideasy_pending_provider_signatures', $pendingSignatures, false);
                update_option('eideasy_pending_provider_lock', false);
            }
        }
    }

    function column_cb($item)
    {
        return sprintf(
            '<input type="checkbox" name="doc_id[]" value="%1$s" />',
            $item['doc_id']
        );
    }
}