<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ReportCardTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportCardTemplateController extends Controller
{
    public function index(): Response
    {
        $templates = ReportCardTemplate::orderBy('is_default', 'desc')
            ->orderBy('name')
            ->get();

        return Inertia::render('admin/report-card-templates/index', [
            'templates' => $templates,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/report-card-templates/edit', [
            'template' => null,
            'isCreate' => true,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'config' => ['required', 'array'],
            'config.layout' => ['required', 'array'],
            'config.blocks' => ['required', 'array'],
            'config.style' => ['required', 'array'],
            'config.images' => ['required', 'array'],
        ]);

        $config = $this->mergeDefaultConfig($request->config);
        $isDefault = $request->boolean('is_default');

        if ($isDefault) {
            ReportCardTemplate::where('is_default', true)->update(['is_default' => false]);
        }

        ReportCardTemplate::create([
            'name' => $request->name,
            'is_default' => $isDefault,
            'config' => $config,
        ]);

        return redirect()->route('admin.report-card-templates.index')
            ->with('success', 'Template berhasil dibuat.');
    }

    public function edit(ReportCardTemplate $reportCardTemplate): Response
    {
        return Inertia::render('admin/report-card-templates/edit', [
            'template' => $reportCardTemplate,
            'isCreate' => false,
        ]);
    }

    public function design(ReportCardTemplate $reportCardTemplate): Response
    {
        return Inertia::render('admin/report-card-templates/design', [
            'template' => $reportCardTemplate,
        ]);
    }

    public function update(Request $request, ReportCardTemplate $reportCardTemplate): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'config' => ['required', 'array'],
        ]);

        $config = $request->config;

        if (! ($request->boolean('_design') ?? false)) {
            $request->validate([
                'config.layout' => ['required', 'array'],
                'config.blocks' => ['required', 'array'],
                'config.style' => ['required', 'array'],
                'config.images' => ['required', 'array'],
            ]);
            $config = $this->mergeDefaultConfig($config);
        } else {
            $existing = $reportCardTemplate->config ?? [];
            $config = array_merge($existing, $config);
            $config['editor_type'] = ReportCardTemplate::EDITOR_CANVA;
            $config['canva'] = $config['canva'] ?? ['html' => null, 'css' => null];
        }
        $isDefault = $request->boolean('is_default');

        if ($isDefault) {
            ReportCardTemplate::where('is_default', true)
                ->where('id', '!=', $reportCardTemplate->id)
                ->update(['is_default' => false]);
        }

        $reportCardTemplate->update([
            'name' => $request->name,
            'is_default' => $isDefault,
            'config' => $config,
        ]);

        return redirect()->route('admin.report-card-templates.index')
            ->with('success', 'Template berhasil diperbarui.');
    }

    public function setDefault(ReportCardTemplate $reportCardTemplate): RedirectResponse
    {
        ReportCardTemplate::where('is_default', true)->update(['is_default' => false]);
        $reportCardTemplate->update(['is_default' => true]);

        return redirect()->back()->with('success', 'Template default berhasil diubah.');
    }

    private function mergeDefaultConfig(array $config): array
    {
        $default = ReportCardTemplate::defaultConfig();

        return [
            'layout' => $config['layout'] ?? $default['layout'],
            'blocks' => array_merge($default['blocks'], $config['blocks'] ?? []),
            'style' => array_merge($default['style'], $config['style'] ?? []),
            'images' => array_merge($default['images'], $config['images'] ?? []),
        ];
    }
}
