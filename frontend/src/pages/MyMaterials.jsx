import React, { useState, useEffect } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import { useAppStore } from '../store/appStore';
import { materialService } from '../services/api';
import { Card, Input, Button, Badge, ProgressBar } from '../components/Shared';
import { Search, Filter, BookOpen, Calendar, HelpCircle, FileText, Upload, ChevronRight, GraduationCap } from 'lucide-react';

export default function MyMaterials() {
  const navigate = useNavigate();
  const { addToast } = useAppStore();
  
  const [materials, setMaterials] = useState([]);
  const [isLoading, setIsLoading] = useState(true);
  
  // Search & Filter state
  const [search, setSearch] = useState('');
  const [statusFilter, setStatusFilter] = useState('');
  const [sort, setSort] = useState('recent');
  
  // Upload modal state
  const [isUploadOpen, setIsUploadOpen] = useState(false);
  const [file, setFile] = useState(null);
  const [uploadTitle, setUploadTitle] = useState('');
  const [uploadDesc, setUploadDesc] = useState('');
  const [uploadingState, setUploadingState] = useState('idle');
  const [uploadMessage, setUploadMessage] = useState('');
  const [uploadPercent, setUploadPercent] = useState(0);

  // Fetch materials
  const fetchMaterials = async () => {
    setIsLoading(true);
    try {
      const res = await materialService.list(search, statusFilter, sort);
      setMaterials(res.data);
    } catch (err) {
      addToast('Failed to retrieve materials', 'error');
    } finally {
      setIsLoading(false);
    }
  };

  useEffect(() => {
    fetchMaterials();
  }, [search, statusFilter, sort, uploadingState]);

  const handleUploadSubmit = async (e) => {
    e.preventDefault();
    if (!file) {
      addToast('Please select a PDF file first', 'error');
      return;
    }
    setUploadingState('progress');
    try {
      await materialService.upload(file, uploadTitle, uploadDesc, (message, percent) => {
        setUploadMessage(message);
        setUploadPercent(percent);
      });
      setUploadingState('success');
      addToast('Uploaded and analyzed material successfully!', 'success');
      setTimeout(() => {
        setIsUploadOpen(false);
        setFile(null);
        setUploadTitle('');
        setUploadDesc('');
        setUploadingState('idle');
      }, 1000);
    } catch (err) {
      setUploadingState('idle');
      addToast(err.message || 'Upload failed', 'error');
    }
  };

  const handleClearFilters = () => {
    setSearch('');
    setStatusFilter('');
    setSort('recent');
  };

  return (
    <div className="space-y-6">
      {/* Page Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-3xl font-bold font-display text-slate-900 leading-tight">My Materials</h1>
          <p className="text-sm text-slate-650 font-body">Manage and explore your uploaded study files</p>
        </div>
        <Button variant="primary" onClick={() => setIsUploadOpen(true)} className="flex items-center gap-2">
          <Upload className="h-4 w-4" /> Upload Material
        </Button>
      </div>

      {/* Search and Filters Bar */}
      <Card glass className="p-4 flex flex-col md:flex-row gap-4 items-center justify-between border-slate-900/10">
        <div className="w-full md:max-w-md relative">
          <Search className="absolute left-3 top-3 h-4 w-4 text-slate-400" />
          <input
            type="text"
            placeholder="Search material titles..."
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            className="w-full h-10 pl-9 pr-4 bg-white/60 border border-slate-350 font-body text-sm text-slate-900 focus:outline-none focus:border-slate-800 transition-colors"
            style={{ borderRadius: 'var(--radius-control)' }}
          />
        </div>

        <div className="w-full md:w-auto flex flex-wrap gap-3 items-center">
          {/* Status Filter */}
          <div className="flex items-center gap-2">
            <span className="font-mono text-[10px] font-bold text-slate-500 uppercase tracking-wider">Status:</span>
            <select
              value={statusFilter}
              onChange={(e) => setStatusFilter(e.target.value)}
              className="h-10 px-3 bg-white/60 border border-slate-350 font-mono text-xs font-semibold uppercase tracking-wider text-slate-850 focus:outline-none focus:border-slate-850"
              style={{ borderRadius: 'var(--radius-control)' }}
            >
              <option value="">All Status</option>
              <option value="ready">Ready</option>
              <option value="processing">Processing</option>
              <option value="failed">Failed</option>
            </select>
          </div>

          {/* Sort Filter */}
          <div className="flex items-center gap-2">
            <span className="font-mono text-[10px] font-bold text-slate-500 uppercase tracking-wider">Sort:</span>
            <select
              value={sort}
              onChange={(e) => setSort(e.target.value)}
              className="h-10 px-3 bg-white/60 border border-slate-350 font-mono text-xs font-semibold uppercase tracking-wider text-slate-850 focus:outline-none focus:border-slate-850"
              style={{ borderRadius: 'var(--radius-control)' }}
            >
              <option value="recent">Most Recent</option>
              <option value="title">Title (A-Z)</option>
            </select>
          </div>
        </div>
      </Card>

      {/* Materials Grid */}
      {isLoading ? (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          {[1, 2, 3, 4, 5, 6].map(i => (
            <Card glass key={i} className="h-44 flex flex-col justify-between">
              <div className="space-y-3">
                <div className="h-5 bg-slate-300 w-1/2 animate-pulse" />
                <div className="h-4 bg-slate-300 w-full animate-pulse" />
              </div>
              <div className="h-4 bg-slate-300 w-1/4 animate-pulse" />
            </Card>
          ))}
        </div>
      ) : materials.length === 0 ? (
        <Card glass className="text-center py-16 space-y-4">
          <BookOpen className="h-16 w-16 text-slate-400 mx-auto" />
          <h3 className="text-xl font-bold font-display text-slate-900">No matching materials</h3>
          <p className="text-sm text-slate-650 font-body max-w-sm mx-auto">
            Try adjusting your search query, filter conditions, or clear all filters to see results.
          </p>
          <div className="flex gap-2 justify-center">
            <Button variant="ghost" onClick={handleClearFilters}>
              Clear Filters
            </Button>
            <Button variant="primary" onClick={() => setIsUploadOpen(true)}>
              Upload New
            </Button>
          </div>
        </Card>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 animate-in fade-in duration-300">
          {materials.map((mat) => (
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
                    <span className="font-mono text-xs font-bold text-slate-900">{mat.overall_mastery}%</span>
                  </div>
                </div>
                
                <Button variant="ghost" onClick={(e) => {
                  e.stopPropagation();
                  navigate(`/materials/${mat.id}`);
                }} className="h-8 px-3 font-mono text-[10px] text-slate-700 hover:text-slate-900 uppercase">
                  View Detail
                </Button>
              </div>
            </Card>
          ))}
        </div>
      )}

      {/* Upload Dialog / Modal */}
      {isUploadOpen && (
        <div className="fixed inset-0 z-[300] flex items-center justify-center p-4">
          <div className="fixed inset-0 bg-slate-900/40 backdrop-blur-sm" onClick={() => uploadingState === 'idle' && setIsUploadOpen(false)} />
          <Card glass className="relative w-full max-w-md p-6 animate-in zoom-in-95 duration-200">
            <h2 className="text-xl font-bold font-display text-slate-900 mb-4">Upload Study Material</h2>
            
            {uploadingState === 'progress' ? (
              <div className="text-center py-6 space-y-4">
                <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-slate-900 mx-auto" />
                <p className="font-mono text-xs font-bold uppercase tracking-wider text-slate-900">
                  {uploadMessage}
                </p>
                <ProgressBar value={uploadPercent} />
                <span className="font-mono text-[10px] text-slate-500">{uploadPercent}%</span>
              </div>
            ) : uploadingState === 'success' ? (
              <div className="text-center py-6 space-y-4">
                <div className="h-10 w-10 bg-emerald-100 text-emerald-800 rounded-full flex items-center justify-center mx-auto">
                  <GraduationCap className="h-5 w-5" />
                </div>
                <p className="font-mono text-xs font-bold uppercase tracking-wider text-emerald-800">
                  Processing Complete!
                </p>
              </div>
            ) : (
              <form onSubmit={handleUploadSubmit} className="space-y-4">
                <Input
                  label="Title (Optional)"
                  placeholder="e.g. OOP Lecture Notes"
                  value={uploadTitle}
                  onChange={(e) => setUploadTitle(e.target.value)}
                />
                
                <div>
                  <label className="block font-mono text-xs font-semibold text-slate-800 uppercase tracking-wider mb-2">Description</label>
                  <textarea
                    rows={2}
                    placeholder="Short summary of what this covers..."
                    value={uploadDesc}
                    onChange={(e) => setUploadDesc(e.target.value)}
                    className="w-full p-2.5 bg-white/70 border border-slate-300 font-body text-sm text-slate-905 focus:outline-none focus:border-slate-800"
                    style={{ borderRadius: 'var(--radius-control)' }}
                  />
                </div>

                <div>
                  <label className="block font-mono text-xs font-semibold text-slate-800 uppercase tracking-wider mb-2">Select PDF</label>
                  <input
                    type="file"
                    accept="application/pdf"
                    onChange={(e) => setFile(e.target.files[0])}
                    className="w-full text-xs font-mono text-slate-600 file:mr-4 file:py-2 file:px-4 file:border-0 file:text-xs file:font-semibold file:uppercase file:bg-slate-900 file:text-white hover:file:bg-slate-800 cursor-pointer"
                  />
                </div>

                <div className="flex justify-end gap-2 pt-4">
                  <Button variant="ghost" type="button" onClick={() => setIsUploadOpen(false)}>
                    Cancel
                  </Button>
                  <Button variant="primary" type="submit">
                    Start Process
                  </Button>
                </div>
              </form>
            )}
          </Card>
        </div>
      )}
    </div>
  );
}
