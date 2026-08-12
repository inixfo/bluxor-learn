import { useRef, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { AlertCircle, ArrowLeft, ArrowRight, CheckCircle2, FileArchive, Monitor, Rocket, Smartphone, Tablet, Upload } from 'lucide-react';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { Input } from '@/components/ui/Input';
import { useToast } from '@/components/ui/Toast';
import { getPreviewUrl, publishLandingVersion, uploadLandingPage, type LandingVersion } from '@/services/api/landing';

const steps = ['Upload', 'Validate', 'Details', 'Preview', 'Publish'];

export default function AdminUploadLandingPage() {
  const toast = useToast();
  const navigate = useNavigate();
  const inputRef = useRef<HTMLInputElement>(null);
  const [step, setStep] = useState(0);
  const [previewDevice, setPreviewDevice] = useState<'desktop' | 'tablet' | 'mobile'>('desktop');
  const [file, setFile] = useState<File | null>(null);
  const [name, setName] = useState('');
  const [slug, setSlug] = useState('');
  const [primaryProductId, setPrimaryProductId] = useState('');
  const [version, setVersion] = useState<LandingVersion | null>(null);
  const [previewUrl, setPreviewUrl] = useState('');
  const [uploading, setUploading] = useState(false);

  const upload = async () => {
    if (!file) {
      toast({ type: 'error', title: 'Package required', message: 'Choose a ZIP package first.' });
      return;
    }
    setUploading(true);
    try {
      const form = new FormData();
      form.append('package', file);
      if (name) form.append('name', name);
      if (slug) form.append('slug', slug);
      if (primaryProductId) form.append('primary_product_id', primaryProductId);
      const uploaded = await uploadLandingPage(form);
      setVersion(uploaded);
      setStep(1);
      toast({ type: 'success', title: 'Package validated' });
    } catch (error: unknown) {
      const message = error instanceof Error ? error.message : 'Package validation failed.';
      toast({ type: 'error', title: 'Upload failed', message });
    } finally {
      setUploading(false);
    }
  };

  const openPreview = async () => {
    if (!version) return;
    const url = await getPreviewUrl(version.id);
    setPreviewUrl(url);
    window.open(url, '_blank', 'noopener,noreferrer');
  };

  const publish = async () => {
    if (!version) return;
    const page = await publishLandingVersion(version.landing_page_id, version.id);
    toast({ type: 'success', title: 'Landing page published' });
    navigate(`/admin/landing-pages/${page.id}`);
  };

  return (
    <div>
      <div className="mb-6">
        <Link to="/admin/landing-pages" className="mb-2 flex items-center gap-1.5 text-sm text-ink-400 hover:text-brand-600">
          <ArrowLeft className="h-4 w-4" /> Landing Pages
        </Link>
        <h1 className="font-display text-2xl font-bold text-ink-900">Upload Landing Page</h1>
      </div>

      <div className="mb-8 flex items-center gap-2 overflow-x-auto no-scrollbar">
        {steps.map((label, index) => (
          <div key={label} className="flex items-center gap-2">
            <div className={`flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-xs font-bold transition-colors ${
              index < step ? 'bg-success-600 text-white' : index === step ? 'bg-brand-600 text-white' : 'bg-ink-100 text-ink-400'
            }`}>
              {index < step ? <CheckCircle2 className="h-4 w-4" /> : index + 1}
            </div>
            <span className={`shrink-0 text-sm font-medium ${index === step ? 'text-ink-900' : 'text-ink-400'}`}>{label}</span>
            {index < steps.length - 1 && <div className={`h-px w-8 ${index < step ? 'bg-success-400' : 'bg-ink-200'}`} />}
          </div>
        ))}
      </div>

      {step === 0 && (
        <Card className="p-8">
          <div
            onDragOver={(event) => event.preventDefault()}
            onDrop={(event) => {
              event.preventDefault();
              setFile(event.dataTransfer.files[0] || null);
            }}
            className="flex flex-col items-center justify-center rounded-2xl border-2 border-dashed border-ink-200 bg-ink-50/50 px-6 py-16 text-center transition-colors hover:border-brand-300 hover:bg-brand-50/30"
          >
            <input ref={inputRef} type="file" accept=".zip,application/zip" className="hidden" onChange={(event) => setFile(event.target.files?.[0] || null)} />
            <div className="flex h-16 w-16 items-center justify-center rounded-2xl bg-white text-brand-600 shadow-card">
              <Upload className="h-8 w-8" />
            </div>
            <p className="mt-4 text-lg font-semibold text-ink-900">{file ? file.name : 'Drag and drop your ZIP package'}</p>
            <p className="mt-1 text-sm text-ink-400">Schema V2 / HTML, CSS, and assets only / no uploaded JavaScript</p>
            <Button className="mt-5" leftIcon={<FileArchive className="h-4 w-4" />} onClick={() => inputRef.current?.click()}>Browse files</Button>
          </div>
          <div className="mt-6 flex justify-end">
            <Button onClick={() => setStep(2)} disabled={!file} rightIcon={<ArrowRight className="h-4 w-4" />}>Continue</Button>
          </div>
        </Card>
      )}

      {step === 1 && version && (
        <Card className="p-6">
          <h2 className="mb-4 text-base font-bold text-ink-900">Validation Results</h2>
          <div className="space-y-2">
            {(version.validation_report?.checks || []).map((check) => (
              <div key={check.label} className="flex items-center gap-3 rounded-xl border border-ink-100 p-3">
                {check.status === 'pass' ? <CheckCircle2 className="h-5 w-5 text-success-600" /> : <AlertCircle className="h-5 w-5 text-danger-500" />}
                <span className="flex-1 text-sm font-medium text-ink-900">{check.label}</span>
                <Badge tone={check.status === 'pass' ? 'success' : 'danger'}>{check.status}</Badge>
              </div>
            ))}
          </div>
          <div className="mt-6 flex justify-between">
            <Button variant="outline" onClick={() => setStep(0)} leftIcon={<ArrowLeft className="h-4 w-4" />}>Back</Button>
            <Button onClick={() => setStep(3)} rightIcon={<ArrowRight className="h-4 w-4" />}>Continue</Button>
          </div>
        </Card>
      )}

      {step === 2 && (
        <Card className="p-6">
          <h2 className="mb-4 text-base font-bold text-ink-900">Landing Page Details</h2>
          <div className="grid gap-4 sm:grid-cols-2">
            <Input label="Name" placeholder="e.g. n8n Automation Sales Page" className="sm:col-span-2" value={name} onChange={(event) => setName(event.target.value)} />
            <Input label="Slug" placeholder="n8n-automation" hint="/go/n8n-automation" value={slug} onChange={(event) => setSlug(event.target.value)} />
            <Input label="Primary product ID" placeholder="1" value={primaryProductId} onChange={(event) => setPrimaryProductId(event.target.value)} />
          </div>
          <div className="mt-6 flex justify-between">
            <Button variant="outline" onClick={() => setStep(0)} leftIcon={<ArrowLeft className="h-4 w-4" />}>Back</Button>
            <Button onClick={upload} loading={uploading} rightIcon={<ArrowRight className="h-4 w-4" />}>Validate Package</Button>
          </div>
        </Card>
      )}

      {step === 3 && (
        <Card className="p-6">
          <div className="mb-4 flex items-center justify-between">
            <h2 className="text-base font-bold text-ink-900">Preview</h2>
            <div className="flex gap-1 rounded-lg border border-ink-200 p-1">
              {([['desktop', Monitor], ['tablet', Tablet], ['mobile', Smartphone]] as const).map(([device, Icon]) => (
                <button key={device} onClick={() => setPreviewDevice(device)}
                  className={`flex h-8 w-8 items-center justify-center rounded-md transition-colors ${previewDevice === device ? 'bg-brand-600 text-white' : 'text-ink-400 hover:bg-ink-100'}`}>
                  <Icon className="h-4 w-4" />
                </button>
              ))}
            </div>
          </div>
          <div className="rounded-xl border border-ink-200 bg-ink-50 p-6 text-center">
            <p className="text-sm text-ink-600">Open the server-rendered package preview before publishing.</p>
            <Button className="mt-4" variant="outline" onClick={openPreview}>Open Preview</Button>
            {previewUrl && <p className="mt-3 truncate text-xs text-ink-400">{previewUrl}</p>}
          </div>
          <div className="mt-6 flex justify-between">
            <Button variant="outline" onClick={() => setStep(1)} leftIcon={<ArrowLeft className="h-4 w-4" />}>Back</Button>
            <Button onClick={() => setStep(4)} rightIcon={<ArrowRight className="h-4 w-4" />}>Continue</Button>
          </div>
        </Card>
      )}

      {step === 4 && (
        <Card className="p-8 text-center">
          <div className="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-success-100 text-success-600">
            <Rocket className="h-8 w-8" />
          </div>
          <h2 className="text-xl font-bold text-ink-900">Ready to publish</h2>
          <p className="mt-1 text-sm text-ink-500">Publishing switches the public URL to this validated version.</p>
          <div className="mt-6 flex justify-center gap-3">
            <Button variant="outline" onClick={() => setStep(3)} leftIcon={<ArrowLeft className="h-4 w-4" />}>Back</Button>
            <Button onClick={publish} leftIcon={<Rocket className="h-4 w-4" />}>Publish Now</Button>
          </div>
        </Card>
      )}
    </div>
  );
}
