import { Link } from 'react-router-dom';
import { Home, Search } from 'lucide-react';
import { Button } from '@/components/ui/Button';

export default function NotFound() {
  return (
    <div className="flex min-h-screen flex-col items-center justify-center bg-ink-50 px-4 text-center">
      <p className="font-display text-8xl font-extrabold text-gradient">404</p>
      <h1 className="mt-4 text-2xl font-bold text-ink-900">Page not found</h1>
      <p className="mt-2 max-w-md text-ink-500">
        The page you're looking for doesn't exist or has been moved.
      </p>
      <div className="mt-6 flex gap-3">
        <Link to="/"><Button leftIcon={<Home className="h-4 w-4" />}>Back to Home</Button></Link>
        <Link to="/products"><Button variant="outline" leftIcon={<Search className="h-4 w-4" />}>Browse Products</Button></Link>
      </div>
    </div>
  );
}
