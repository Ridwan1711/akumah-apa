import { Head, Link, router } from '@inertiajs/react';
import grapesjs from 'grapesjs';
import 'grapesjs/dist/css/grapes.min.css';
import { ArrowLeft, Save } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import FlashMessage from '@/components/flash-message';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

const PROPS_BLOCKS = [
    { id: 'prop_student_name', label: 'Nama Santri', tag: 'span', content: '[Nama Santri]' },
    { id: 'prop_student_nis', label: 'NIS', tag: 'span', content: '[NIS]' },
    { id: 'prop_student_class', label: 'Kelas', tag: 'span', content: '[Kelas]' },
    { id: 'prop_semester', label: 'Semester', tag: 'span', content: '[Semester]' },
    { id: 'prop_grades_table', label: 'Tabel Nilai Kitab', tag: 'div', content: '[Tabel Nilai]' },
    { id: 'prop_violations_table', label: 'Tabel Pelanggaran', tag: 'div', content: '[Tabel Pelanggaran]' },
    { id: 'prop_notes', label: 'Catatan Wali Kelas', tag: 'div', content: '[Catatan]' },
    { id: 'prop_qr', label: 'QR Code', tag: 'div', content: '[QR]' },
];

type ReportCardTemplate = {
    id: number;
    name: string;
    is_default?: boolean;
    config: {
        editor_type?: string;
        canva?: { html: string | null; css: string | null };
    };
};

type Props = {
    template: ReportCardTemplate;
};

const DEFAULT_HTML = `
<body id="raport-body">
  <div class="raport-container">

    <!-- HEADER -->
    <div class="header">
      <h1>PONDOK PESANTREN MANARUL HUDA</h1>
      <h2>Madrasah Diniyah</h2>
      <div class="divider"></div>
    </div>

    <!-- DATA SANTRI -->
    <div class="student-info">
      <div class="info-row">
        <div><strong>Nama</strong></div>
        <div>: <span data-prop="prop_student_name">[Nama Santri]</span></div>
      </div>
      <div class="info-row">
        <div><strong>NIS</strong></div>
        <div>: <span data-prop="prop_student_nis">[NIS]</span></div>
      </div>
      <div class="info-row">
        <div><strong>Kelas</strong></div>
        <div>: <span data-prop="prop_student_class">[Kelas]</span></div>
      </div>
      <div class="info-row">
        <div><strong>Semester</strong></div>
        <div>: <span data-prop="prop_semester">[Semester]</span></div>
      </div>
    </div>

    <!-- NILAI -->
    <div class="section">
      <h3>Nilai Akademik</h3>
      <div data-prop="prop_grades_table">[Tabel Nilai]</div>
    </div>

    <!-- PELANGGARAN -->
    <div class="section">
      <h3>Catatan Pelanggaran</h3>
      <div data-prop="prop_violations_table">[Tabel Pelanggaran]</div>
    </div>

    <!-- CATATAN -->
    <div class="section">
      <h3>Catatan Wali Kelas</h3>
      <div class="notes-box" data-prop="prop_notes">
        [Catatan Wali Kelas]
      </div>
    </div>

    <!-- FOOTER -->
    <div class="footer">
      <div class="signature">
        <p>Wali Kelas</p>
        <br><br><br>
        <p>(___________________)</p>
      </div>

      <div class="qr">
        <div data-prop="prop_qr">[QR]</div>
      </div>
    </div>

  </div>
</body>
`;

const DEFAULT_CSS = `
body {
  margin: 0;
  background: #eaeaea;
  font-family: "Times New Roman", serif;
}

.raport-container {
  width: 595px;
  margin: 30px auto;
  background: white;
  padding: 40px;
  box-shadow: 0 0 10px rgba(0,0,0,0.1);
}

/* HEADER */
.header {
  text-align: center;
}

.header h1 {
  font-size: 20px;
  margin: 0;
  font-weight: bold;
  letter-spacing: 1px;
}

.header h2 {
  margin: 5px 0;
  font-size: 16px;
  font-weight: normal;
}

.semester {
  font-size: 14px;
  margin-top: 5px;
}

.divider {
  border-bottom: 2px solid black;
  margin-top: 15px;
}

/* STUDENT INFO */
.student-info {
  margin-top: 25px;
  font-size: 14px;
  display: grid;
  grid-template-columns: 1fr 1fr;
  margin-bottom: 5px;
}

.info-row {
  display: grid;
  grid-template-columns: 2fr 6fr;
  margin-bottom: 5px;
}

/* SECTION */
.section {
  margin-top: 25px;
}

.section h3 {
  font-size: 15px;
  margin-bottom: 8px;
  border-bottom: 1px solid #000;
  padding-bottom: 3px;
}

.notes-box {
  border: 1px solid #000;
  min-height: 80px;
  padding: 10px;
}

/* FOOTER */
.footer {
  margin-top: 40px;
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
}

.signature {
  text-align: center;
  font-size: 14px;
}

.qr {
  text-align: right;
}
`;

export default function ReportCardTemplateDesign({ template }: Props) {
    const editorRef = useRef<HTMLDivElement>(null);
    const grapesRef = useRef<ReturnType<typeof grapesjs.init> | null>(null);
    const [saving, setSaving] = useState(false);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Raport', href: '/admin/report-cards' },
        { title: 'Template Raport', href: '/admin/report-card-templates' },
        { title: `Desain: ${template.name}`, href: '#' },
    ];

    useEffect(() => {
        if (!editorRef.current) return;

        const canva = template.config?.canva;
        const initialHtml = canva?.html || DEFAULT_HTML;
        const initialCss = canva?.css || DEFAULT_CSS;

        const editor = grapesjs.init({
            container: editorRef.current,
            fromElement: false,
            storageManager: false,
            height: 'calc(100vh - 220px)',
            width: 'auto',
            blockManager: { appendTo: '#blocks' },
            styleManager: { appendTo: '#styles' },
            layerManager: { appendTo: '#layers' },
            deviceManager: { devices: [{ name: 'Desktop', width: '595px' }] },
            canvas: { styles: [initialCss], scripts: [] },
        });

        const domComp = editor.DomComponents;
        PROPS_BLOCKS.forEach(({ id, label, content }) => {
            domComp.addType(id, {
                model: {
                    defaults: {
                        tagName: 'span',
                        editable: false,
                        draggable: true,
                        droppable: false,
                        attributes: { 'data-prop': id },
                        content: content,
                        style: { padding: '2px 4px', background: '#e8e8e8', borderRadius: '2px', display: 'inline-block' },
                    },
                },
                isComponent(el: HTMLElement) {
                    return el.getAttribute?.('data-prop') === id;
                },
            });
            editor.BlockManager.add(id, {
                label,
                content: { type: id },
                category: 'Data (Tidak Dapat Diedit)',
            });
        });

        domComp.addType('prop-div', {
            extend: 'div',
            model: {
                defaults: {
                    editable: false,
                    draggable: true,
                    droppable: false,
                    attributes: { class: 'gjs-prop-div' },
                    style: { padding: '4px 8px', background: '#e8e8e8', borderRadius: '2px', marginBottom: '8px' },
                },
                isComponent(el: HTMLElement) {
                    const prop = el.getAttribute?.('data-prop');
                    return prop && ['prop_grades_table', 'prop_violations_table', 'prop_notes', 'prop_qr'].includes(prop);
                },
            },
        });

        editor.setComponents(initialHtml);
        editor.setStyle(initialCss);

        grapesRef.current = editor;

        return () => {
            editor.destroy();
            grapesRef.current = null;
        };
    }, [template.id, template.name]);

    function handleSave() {
        const editor = grapesRef.current;
        if (!editor) return;

        setSaving(true);
        const html = editor.getHtml();
        const css = editor.getCss();

        const config = { ...template.config };
        config.editor_type = 'canva';
        config.canva = { html: html ?? null, css: css ?? null };

        router.put(`/admin/report-card-templates/${template.id}`, {
            name: template.name,
            is_default: template.is_default ?? false,
            config,
            _design: true,
        }, {
            preserveState: true,
            onFinish: () => setSaving(false),
            onSuccess: () => setSaving(false),
        });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Desain Template: ${template.name}`} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <Heading title={`Desain Canva: ${template.name}`} description="Edit teks bebas. Blok data (abu-abu) akan diisi otomatis saat cetak PDF." />
                    <div className="flex gap-2">
                        <Button variant="outline" asChild>
                            <Link href={`/admin/report-card-templates/${template.id}/edit`}>
                                <ArrowLeft className="mr-1 size-4" /> Kembali ke Edit
                            </Link>
                        </Button>
                        <Button onClick={handleSave} disabled={saving}>
                            {saving && <Spinner />}
                            <Save className="mr-1 size-4" /> Simpan Desain
                        </Button>
                    </div>
                </div>
                <FlashMessage />

                <div className="flex gap-4 rounded-lg border p-2" style={{ minHeight: '500px' }}>
                    <div className="w-48 shrink-0 space-y-2 border-r pr-2">
                        <div id="blocks" className="text-sm font-medium" />
                    </div>
                    <div className="flex-1 overflow-auto">
                        <div ref={editorRef} id="gjs-editor" />
                    </div>
                    <div className="w-48 shrink-0 border-l pl-2">
                        <div id="layers" className="mb-2 text-sm font-medium">Layers</div>
                        <div id="styles" className="text-sm">Styles</div>
                    </div>
                </div>

                <p className="text-xs text-muted-foreground">
                    Teks biasa dapat diedit (misal: &quot;Madrasah Diniyah&quot; → &quot;Madrasah Aliyyah&quot;). Blok dari kategori &quot;Data&quot; tidak dapat diedit—akan diisi oleh sistem saat cetak.
                </p>
            </div>
        </AppLayout>
    );
}
