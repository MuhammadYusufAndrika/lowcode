<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflows', function (Blueprint $table) {
            $table->json('draft_definition')->nullable();
            $table->unsignedInteger('draft_revision')->default(0);
        });
        Schema::create('workflow_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->json('definition');
            $table->foreignId('published_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['workflow_id', 'version']);
        });
        Schema::create('workflow_instances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained()->restrictOnDelete();
            $table->foreignId('workflow_version_id')->constrained()->restrictOnDelete();
            $table->foreignId('requester_id')->constrained('users')->restrictOnDelete();
            $table->uuid('idempotency_key');
            $table->string('status')->index();
            $table->string('current_node_id')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['requester_id', 'idempotency_key']);
        });
        Schema::create('workflow_instance_data', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_instance_id')->constrained()->restrictOnDelete();
            $table->string('key', 64);
            $table->json('value')->nullable();
            $table->timestamps();
            $table->unique(['workflow_instance_id', 'key']);
        });
        Schema::create('workflow_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_instance_id')->constrained()->restrictOnDelete();
            $table->string('node_id');
            $table->string('node_name');
            $table->string('assignee_type');
            $table->unsignedBigInteger('assignee_id');
            $table->foreignId('escalation_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('status')->default('pending')->index();
            $table->text('comment')->nullable();
            $table->timestamp('due_at')->nullable()->index();
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('reminded_at')->nullable();
            $table->timestamp('escalated_at')->nullable();
            $table->timestamps();
            $table->unique(['workflow_instance_id', 'node_id']);
        });
        Schema::create('workflow_execution_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_instance_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('action');
            $table->string('node_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
        Schema::create('workflow_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('workflow_instance_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('message', 5000);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('action');
            $table->string('target_type');
            $table->unsignedBigInteger('target_id');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
        Schema::create('workflow_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_instance_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('field_key', 64);
            $table->string('path');
            $table->string('original_name');
            $table->string('mime');
            $table->unsignedBigInteger('size');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['workflow_files', 'audit_logs', 'workflow_notifications', 'workflow_execution_logs', 'workflow_tasks', 'workflow_instance_data', 'workflow_instances', 'workflow_versions'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('workflows', fn (Blueprint $table) => $table->dropColumn(['draft_definition', 'draft_revision']));
    }
};
