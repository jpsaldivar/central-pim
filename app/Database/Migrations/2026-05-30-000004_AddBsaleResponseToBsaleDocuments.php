<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddBsaleResponseToBsaleDocuments extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('bsale_documents', [
            'bsale_response' => [
                'type'    => 'JSON',
                'null'    => true,
                'default' => null,
                'after'   => 'bsale_document_url',
            ],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('bsale_documents', 'bsale_response');
    }
}
