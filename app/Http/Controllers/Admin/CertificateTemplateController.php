<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CertificateTemplate;
use Illuminate\Http\Request;

class CertificateTemplateController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'template_content' => 'required|string',
            'is_default' => 'boolean',
        ]);

        // If setting as default, unset other defaults
        if ($validated['is_default'] ?? false) {
            CertificateTemplate::where('is_default', true)->update(['is_default' => false]);
        }

        CertificateTemplate::create($validated);

        return redirect()->route('admin.classroom.certificates')
            ->with('success', 'Template created successfully.');
    }

    public function update(Request $request, CertificateTemplate $template)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'template_content' => 'required|string',
            'is_default' => 'boolean',
        ]);

        // If setting as default, unset other defaults
        if ($validated['is_default'] ?? false) {
            CertificateTemplate::where('is_default', true)
                ->where('id', '!=', $template->id)
                ->update(['is_default' => false]);
        }

        $template->update($validated);

        return redirect()->route('admin.classroom.certificates')
            ->with('success', 'Template updated successfully.');
    }

    public function destroy(CertificateTemplate $template)
    {
        if ($template->is_default) {
            return redirect()->route('admin.classroom.certificates')
                ->with('error', 'Cannot delete the default template.');
        }

        $template->delete();

        return redirect()->route('admin.classroom.certificates')
            ->with('success', 'Template deleted successfully.');
    }

    public function setDefault(CertificateTemplate $template)
    {
        // Unset all other defaults
        CertificateTemplate::where('is_default', true)->update(['is_default' => false]);

        // Set this template as default
        $template->update(['is_default' => true]);

        return redirect()->route('admin.classroom.certificates')
            ->with('success', 'Default template updated successfully.');
    }
}
