import React, { useState, useEffect } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import { useAppStore } from '../store/appStore';
import { materialService } from '../services/api';
import { Card, Button, ProgressBar, Badge } from '../components/Shared';
import { formatPercentage } from '../utils/format';
import { Upload, FileText, ChevronRight, User, BookOpen, GraduationCap, Award } from 'lucide-react';

export default function Home() {
  const navigate = useNavigate();
  const { user, addToast } = useAppStore();

  const [recentMaterials, setRecentMaterials] = useState([]);
  const [isLoading, setIsLoading] = useState(true);

  // Upload States
  const [isDragging, setIsDragging] = useState(false);
  const [uploadingState, setUploadingState] = useState('idle'); // 'idle' | 'progress' | 'success'
  const [uploadMessage, setUploadMessage] = useState('');
  const [uploadPercent, setUploadPercent] = useState(0);

  // Load Recent Materials
  useEffect(() => {
    async function loadData() {
      setIsLoading(true);
      try {
        const res = await materialService.list('', '', 'recent');
        setRecentMaterials(res.data.slice(0, 5));
      } catch (err) {
        addToast('Failed to load recent materials', 'error');
      } finally {
        setIsLoading(false);
      }
    }
    loadData();
  }, [addToast, uploadingState]);

  // Drag and drop handlers
  const handleDragOver = (e) => {
    e.preventDefault();
    setIsDragging(true);
  };

  const handleDragLeave = () => {
    setIsDragging(false);
  };

  const handleDrop = async (e) => {
    e.preventDefault();
    setIsDragging(false);

    const files = e.dataTransfer.files;
    if (files.length > 0) {
      await uploadFile(files[0]);
    }
  };

  const handleFileChange = async (e) => {
    const files = e.target.files;
    if (files.length > 0) {
      await uploadFile(files[0]);
    }
  };

  const uploadFile = async (file) => {
    if (file.type !== 'application/pdf') {
      addToast('Please upload a PDF file only.', 'error');
      return;
    }

    setUploadingState('progress');
    try {
      const result = await materialService.upload(file, null, null, (message, percent) => {
        setUploadMessage(message);
        setUploadPercent(percent);
      });
      setUploadingState('success');
      addToast('Material uploaded successfully!', 'success');

      // Auto redirect to new material detail or let user click Start study session
      setTimeout(() => {
        setUploadingState('idle');
        navigate(`/materials/${result.id}`);
      }, 1000);
    } catch (err) {
      setUploadingState('idle');
      addToast(err.message || 'File upload failed', 'error');
    }
  };

  // Calculate stats
  const totalMaterials = recentMaterials.length;
  const averageMastery = recentMaterials.length > 0
    ? Math.round(recentMaterials.reduce((acc, m) => acc + m.overall_mastery, 0) / recentMaterials.length)
    : 0;

  return (
    <div className="space-y-12">
      {/* Hero Section & Profile Row */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-8 items-start">
        {/* Left 2 Columns: Headline & Uploader */}
        <div className="lg:col-span-2 space-y-6">
          <div>
            <p className="mb-6 text-sm font-medium uppercase tracking-[0.2em] text-teal-900">
              AI-Powered Learning
            </p>

            <h1 className="text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight leading-[1.02]">
              <span className="block text-slate-900">Study smarter.</span>
              <span className="block text-cyan-900">Master anything.</span>
            </h1>
            <p className="mt-8 max-w-3xl text-lg sm:text-xl leading-relaxed text-slate-600">
              Upload your materials and Studyback turns them into a personalized
              learning experience — teach, quiz, review, and track mastery all in one
              place.
            </p>
          </div>

          {/* Upload Widget */}
          {uploadingState === 'progress' ? (
            <Card glass className="p-8 border-slate-900/20 text-center space-y-4 min-h-[220px] flex flex-col justify-center">
              <div className="flex justify-center">
                <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-slate-900" />
              </div>
              <p className="font-mono text-xs font-bold uppercase tracking-wider text-slate-900">
                {uploadMessage}
              </p>
              <div className="max-w-md mx-auto w-full">
                <ProgressBar value={uploadPercent} />
                <span className="font-mono text-[10px] text-slate-500 mt-2 block">{uploadPercent}%</span>
              </div>
            </Card>
          ) : uploadingState === 'success' ? (
            <Card glass className="p-8 border-emerald-500/20 text-center space-y-4 min-h-[220px] flex flex-col justify-center">
              <div className="h-12 w-12 bg-emerald-100 text-emerald-800 rounded-full flex items-center justify-center mx-auto">
                <GraduationCap className="h-6 w-6" />
              </div>
              <p className="font-mono text-xs font-bold uppercase tracking-wider text-emerald-800">
                Processing Complete! Redirecting...
              </p>
            </Card>
          ) : (
            <div
              onDragOver={handleDragOver}
              onDragLeave={handleDragLeave}
              onDrop={handleDrop}
              className={`border-2 border-dashed transition-all p-8 text-center min-h-[220px] flex flex-col justify-center items-center cursor-pointer ${isDragging
                ? 'border-slate-900 bg-white/40 shadow-inner'
                : 'border-slate-350 bg-white/20 backdrop-blur-md hover:bg-white/30'
                }`}
              style={{ borderRadius: 'var(--radius-glass)' }}
              onClick={() => document.getElementById('file-upload').click()}
            >
              <input
                id="file-upload"
                type="file"
                className="hidden"
                accept="application/pdf"
                onChange={handleFileChange}
              />
              <div className="h-12 w-12 bg-slate-900/5 text-slate-900 flex items-center justify-center mb-4" style={{ borderRadius: 'var(--radius-control)' }}>
                <Upload className="h-6 w-6" />
              </div>
              <p className="font-mono text-xs font-bold uppercase tracking-wider text-slate-900 mb-1">
                Drag a PDF here or click to browse
              </p>
              <p className="text-xs text-slate-500 font-body">
                Supports slide decks, syllabus, or lecture PDFs up to 20MB
              </p>
            </div>
          )}
        </div>

        {/* Right 1 Column: Profile stats Panel */}
        <Card glass className="p-6 border-slate-900/10 flex flex-col justify-between min-h-[360px]">
          <div className="space-y-6">
            <div className="flex items-center gap-3">
              <div className="h-10 w-10 bg-slate-900/10 flex items-center justify-center text-slate-900" style={{ borderRadius: 'var(--radius-control)' }}>
                <User className="h-5 w-5" />
              </div>
              <div>
                <h3 className="font-display font-semibold text-base text-slate-900 leading-tight">
                  {user?.name}
                </h3>
                <p className="font-mono text-[10px] text-slate-500 uppercase tracking-wider">{user?.email}</p>
              </div>
            </div>

            <div className="border-t border-slate-900/10 pt-6 space-y-4">
              <div className="flex justify-between items-center">
                <span className="font-mono text-xs text-slate-600 uppercase tracking-wider flex items-center gap-1.5">
                  <BookOpen className="h-4 w-4" /> Materials
                </span>
                <span className="font-mono text-lg font-bold text-slate-900">{totalMaterials}</span>
              </div>
              <div className="flex justify-between items-center">
                <span className="font-mono text-xs text-slate-600 uppercase tracking-wider flex items-center gap-1.5">
                  <Award className="h-4 w-4" /> Avg Mastery
                </span>
                <span className="font-mono text-lg font-bold text-slate-900">{averageMastery}%</span>
              </div>
            </div>
          </div>

          <div className="pt-6">
            <Link to="/materials">
              <Button variant="ghost" className="w-full text-center">
                Open Material Library
              </Button>
            </Link>
          </div>
        </Card>
      </div>

      {/* Recent Materials Section */}
      <div className="space-y-4">
        <div className="flex items-center justify-between">
          <h2 className="text-2xl font-bold font-display text-slate-900">Recent Study Materials</h2>
          <Link to="/materials" className="font-mono text-xs font-semibold uppercase tracking-wider text-slate-700 hover:text-slate-900 flex items-center">
            View All <ChevronRight className="h-4 w-4 ml-0.5" />
          </Link>
        </div>

        {isLoading ? (
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            {[1, 2, 3].map(i => (
              <Card glass key={i} className="h-40 flex flex-col justify-between">
                <div className="space-y-3">
                  <div className="h-5 bg-slate-300 w-2/3 animate-pulse" />
                  <div className="h-4 bg-slate-300 w-full animate-pulse" />
                </div>
                <div className="h-4 bg-slate-300 w-1/3 animate-pulse" />
              </Card>
            ))}
          </div>
        ) : recentMaterials.length === 0 ? (
          <Card glass className="text-center py-12 space-y-4">
            <FileText className="h-12 w-12 text-slate-500 mx-auto" />
            <h3 className="text-lg font-bold font-display text-slate-900">No Study Materials Yet</h3>
            <p className="text-sm text-slate-600 font-body max-w-sm mx-auto">
              Ready to learn? Drag your first PDF material into the uploader above to get started.
            </p>
          </Card>
        ) : (
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            {recentMaterials.map((mat) => (
              <Card
                glass
                key={mat.id}
                className="flex flex-col justify-between hover:-translate-y-0.5"
                onClick={() => navigate(`/materials/${mat.id}`)}
              >
                <div className="space-y-2">
                  <div className="flex items-start justify-between">
                    <h3 className="font-display font-semibold text-lg text-slate-900 leading-tight line-clamp-1 hover:underline cursor-pointer">
                      {mat.title}
                    </h3>
                    <Badge variant={mat.status === 'ready' ? 'success' : mat.status === 'failed' ? 'error' : 'warning'}>
                      {mat.status}
                    </Badge>
                  </div>
                  <p className="text-sm text-slate-600 font-body line-clamp-2">
                    {mat.description}
                  </p>
                </div>

                <div className="border-t border-slate-900/10 pt-4 mt-6 flex justify-between items-center">
                  <div className="flex items-center gap-4">
                    <div className="flex flex-col">
                      <span className="font-mono text-[9px] text-slate-500 uppercase tracking-wider">Topics</span>
                      <span className="font-mono text-xs font-bold text-slate-900">{mat.topics_count}</span>
                    </div>
                    <div className="flex flex-col">
                      <span className="font-mono text-[9px] text-slate-500 uppercase tracking-wider">Mastery</span>
                      <span className="font-mono text-xs font-bold text-slate-900">{formatPercentage(mat.overall_mastery)}%</span>
                    </div>
                  </div>

                  <Link to={`/materials/${mat.id}`}>
                    <Button variant="ghost" className="h-8 px-3 font-mono text-[10px] text-slate-700 hover:text-slate-900 uppercase">
                      Open
                    </Button>
                  </Link>
                </div>
              </Card>
            ))}
          </div>
        )}
      </div>
    </div>
  );
}
