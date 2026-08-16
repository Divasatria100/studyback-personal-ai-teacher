import React, { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { useAppStore } from '../store/appStore';
import { materialService, studySessionService } from '../services/api';
import { Card, Button, Badge, ProgressBar, Modal } from '../components/Shared';
import { formatPercentage } from '../utils/format';
import { Download, Play, BookOpen, Calendar, Award, FileText, ChevronRight, Check, Trash2 } from 'lucide-react';

export default function MaterialDetail() {
  const { id } = useParams();
  const navigate = useNavigate();
  const { addToast } = useAppStore();

  const [material, setMaterial] = useState(null);
  const [topicsData, setTopicsData] = useState(null);
  const [isLoading, setIsLoading] = useState(true);

  // Configuration Modal State
  const [isConfigOpen, setIsConfigOpen] = useState(false);
  const [selectedTopics, setSelectedTopics] = useState([]);
  const [selectedMode, setSelectedMode] = useState('guided_study_session');
  const [selectedDifficulty, setSelectedDifficulty] = useState('medium');
  const [isStartingSession, setIsStartingSession] = useState(false);

  // Delete state
  const [isDeleteOpen, setIsDeleteOpen] = useState(false);
  const [isDeleting, setIsDeleting] = useState(false);

  useEffect(() => {
    async function loadData() {
      setIsLoading(true);
      try {
        const mat = await materialService.get(id);
        setMaterial(mat);
        const top = await materialService.getTopics(id);
        setTopicsData(top);
        
        // Auto select all topics by default in configuration modal
        if (top && top.topics) {
          setSelectedTopics(top.topics.map(t => t.id));
        }
      } catch (err) {
        addToast(err.message || 'Error loading material detail', 'error');
      } finally {
        setIsLoading(false);
      }
    }
    loadData();
  }, [id, addToast]);

  const handleDownload = async () => {
    try {
      await materialService.download(id);
      addToast('Download started', 'success');
    } catch (err) {
      addToast('Download failed', 'error');
    }
  };

  const handleDeleteMaterial = async () => {
    if (isDeleting) return;
    setIsDeleting(true);
    try {
      await materialService.delete(id);
      addToast('Material deleted successfully', 'success');
      navigate('/materials');
    } catch (err) {
      addToast(err.message || 'Failed to delete this material. Please try again.', 'error');
      setIsDeleteOpen(false);
    } finally {
      setIsDeleting(false);
    }
  };

  const toggleTopicSelect = (topicId) => {
    setSelectedTopics(prev => 
      prev.includes(topicId) 
        ? prev.filter(tid => tid !== topicId) 
        : [...prev, topicId]
    );
  };

  const handleStartSession = async (e) => {
    e.preventDefault();
    if (selectedTopics.length === 0) {
      addToast('Please select at least one topic to study', 'error');
      return;
    }
    setIsStartingSession(true);
    try {
      const session = await studySessionService.create(
        id, 
        selectedMode, 
        selectedDifficulty, 
        selectedTopics
      );
      addToast('Study session initialized!', 'success');
      setIsConfigOpen(false);
      navigate(`/workspace/${session.id}`);
    } catch (err) {
      addToast('Failed to start study session', 'error');
    } finally {
      setIsStartingSession(false);
    }
  };

  if (isLoading) {
    return (
      <div className="space-y-6 animate-pulse">
        <div className="h-10 bg-slate-350 w-1/3 rounded" />
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
          <div className="lg:col-span-2 h-64 bg-slate-350 rounded" />
          <div className="h-64 bg-slate-350 rounded" />
        </div>
      </div>
    );
  }

  if (!material) return null;

  return (
    <div className="space-y-8 animate-in fade-in duration-300">
      {/* Back button */}
      <div>
        <Button variant="ghost" onClick={() => navigate('/materials')} className="font-mono text-xs font-semibold uppercase">
          &larr; Back to Library
        </Button>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-8 items-start">
        {/* Left Column: Material Info & Actions */}
        <div className="lg:col-span-2 space-y-6">
          <Card glass className="p-6 border-slate-900/10 space-y-6">
            <div className="space-y-3">
              <div className="flex items-center justify-between">
                <span className="font-mono text-[10px] text-slate-500 uppercase tracking-wider">Material Document</span>
                <Badge variant={material.status === 'ready' ? 'success' : material.status === 'failed' ? 'error' : 'warning'}>
                  {material.status === 'ready' ? 'Analyzed' : material.status}
                </Badge>
              </div>
              <h1 className="text-3xl font-bold font-display text-slate-900 leading-tight">
                {material.title}
              </h1>
              <p className="text-base text-slate-650 font-body">
                {material.description}
              </p>
            </div>

            <div className="border-t border-slate-900/10 pt-6 grid grid-cols-2 gap-4">
              <div>
                <span className="block font-mono text-[9px] text-slate-500 uppercase tracking-wider">Filename</span>
                <span className="font-mono text-xs font-semibold text-slate-800 line-clamp-1">{material.original_filename}</span>
              </div>
              <div>
                <span className="block font-mono text-[9px] text-slate-500 uppercase tracking-wider">File Size</span>
                <span className="font-mono text-xs font-semibold text-slate-800">
                  {Math.round(material.file_size_bytes / 1024)} KB
                </span>
              </div>
            </div>

            <div className="flex flex-wrap gap-3 pt-2">
              <Button variant="primary" onClick={() => setIsConfigOpen(true)} className="flex items-center gap-2">
                <Play className="h-4 w-4 fill-current" /> Start Study Session
              </Button>
              <Button variant="secondary" onClick={handleDownload} className="flex items-center gap-2">
                <Download className="h-4 w-4" /> Download PDF
              </Button>
              <Button variant="danger" onClick={() => setIsDeleteOpen(true)} className="flex items-center gap-2">
                <Trash2 className="h-4 w-4" /> Delete
              </Button>
            </div>
          </Card>

          {/* Topics Accordion list */}
          <div className="space-y-4">
            <h2 className="text-xl font-bold font-display text-slate-900">Topic Outline</h2>
            <div className="space-y-3">
              {topicsData?.topics.map((t, idx) => (
                <Card glass key={t.id} className="p-4 border-slate-900/10">
                  <div className="flex items-start justify-between">
                    <div>
                      <h3 className="font-display font-semibold text-base text-slate-900 leading-tight">
                        {idx + 1}. {t.name}
                      </h3>
                      <p className="text-sm text-slate-600 font-body mt-1">
                        {t.description}
                      </p>
                    </div>
                    {t.subtopics.length > 0 ? (
                      <Badge variant="neutral">{t.subtopics.length} Subtopics</Badge>
                    ) : (
                      <Badge
                        variant={
                          t.status === 'mastered' ? 'success' :
                          t.status === 'needs_review' ? 'error' :
                          t.status === 'in_progress' ? 'warning' : 'neutral'
                        }
                        className="text-[9px]"
                      >
                        {t.status.replace('_', ' ')}
                      </Badge>
                    )}
                  </div>

                  {t.subtopics.length > 0 ? (
                    /* Subtopics Nested list */
                    <div className="mt-4 pl-4 border-l-2 border-slate-300 space-y-2">
                      {t.subtopics.map(sub => (
                        <div key={sub.id} className="flex justify-between items-center text-xs">
                          <span className="font-body text-slate-700">{sub.name}</span>
                          <div className="flex items-center gap-2">
                            <span className="font-mono text-[10px] text-slate-500">{formatPercentage(sub.mastery_score)}% Mastery</span>
                            <Badge
                              variant={
                                sub.status === 'mastered' ? 'success' :
                                sub.status === 'needs_review' ? 'error' :
                                sub.status === 'in_progress' ? 'warning' : 'neutral'
                              }
                              className="text-[9px]"
                            >
                              {sub.status.replace('_', ' ')}
                            </Badge>
                          </div>
                        </div>
                      ))}
                    </div>
                  ) : (
                    /* Topic-only learning target: the topic itself is the concept */
                    <div className="mt-4 pl-4 border-l-2 border-slate-300 flex justify-between items-center text-xs">
                      <span className="font-body text-slate-700 line-clamp-1">Whole topic learning target</span>
                      <div className="flex items-center gap-2">
                        <span className="font-mono text-[10px] text-slate-500">{formatPercentage(t.mastery_score)}% Mastery</span>
                      </div>
                    </div>
                  )}
                </Card>
              ))}
            </div>
          </div>
        </div>

        {/* Right Column: Learning Progress Summary */}
        <Card glass className="p-6 border-slate-900/10 space-y-6 text-center">
          <h2 className="text-lg font-bold font-display text-slate-900">Overall Progress</h2>
          
          <div className="flex justify-center py-4">
            <div className="relative flex items-center justify-center" style={{ width: 120, height: 120 }}>
              <svg className="w-full h-full transform -rotate-90">
                <circle
                  className="text-slate-200/50"
                  strokeWidth="8"
                  stroke="currentColor"
                  fill="transparent"
                  r="50"
                  cx="60"
                  cy="60"
                />
                <circle
                  className="text-slate-900 transition-all duration-300"
                  strokeWidth="8"
                  strokeDasharray={2 * Math.PI * 50}
                  strokeDashoffset={2 * Math.PI * 50 - (material.overall_mastery / 100) * 2 * Math.PI * 50}
                  strokeLinecap="round"
                  stroke="currentColor"
                  fill="transparent"
                  r="50"
                  cx="60"
                  cy="60"
                />
              </svg>
              <span className="absolute font-mono text-xl font-bold text-slate-900">
                {formatPercentage(material.overall_mastery)}%
              </span>
            </div>
          </div>

          <p className="text-sm text-slate-650 font-body">
            {material.overall_mastery === 0 
              ? "You haven't started studying this material yet. Hit start session to begin!" 
              : `You have mastered ${formatPercentage(material.overall_mastery)}% of the content. Keep reviewing to hit 100%!`}
          </p>

          <div className="border-t border-slate-900/10 pt-4 flex flex-col gap-2 text-left text-xs font-mono text-slate-600">
            <div className="flex justify-between">
              <span>Status</span>
              <span className="font-bold text-slate-800 uppercase">
                {material.overall_mastery >= 80 ? 'Mastered' : material.overall_mastery > 0 ? 'In Progress' : 'Not Started'}
              </span>
            </div>
            <div className="flex justify-between">
              <span>Total Subtopics</span>
              <span className="font-bold text-slate-800">
                {topicsData?.topics.reduce((acc, t) => acc + t.subtopics.length, 0) || 0}
              </span>
            </div>
          </div>
        </Card>
      </div>

      {/* Configuration Modal */}
      {isConfigOpen && (
        <div className="fixed inset-0 z-[300] flex items-center justify-center p-4">
          <div className="fixed inset-0 bg-slate-900/40 backdrop-blur-sm" onClick={() => !isStartingSession && setIsConfigOpen(false)} />
          
          <Card glass className="relative w-full max-w-lg p-6 max-h-[90vh] overflow-y-auto flex flex-col animate-in zoom-in-95 duration-200">
            <div className="flex items-center justify-between border-b border-slate-900/10 pb-3 mb-4">
              <h2 className="text-xl font-bold font-display text-slate-900 leading-none">Configure Study Session</h2>
              <button 
                onClick={() => setIsConfigOpen(false)}
                className="text-slate-500 hover:text-slate-900"
              >
                &times;
              </button>
            </div>

            <form onSubmit={handleStartSession} className="space-y-6">
              {/* Step 1: Mode */}
              <div className="space-y-2">
                <span className="block font-mono text-xs font-bold text-slate-800 uppercase tracking-wider">1. Select Learning Mode</span>
                <div className="grid grid-cols-2 gap-3">
                  {[
                    { id: 'guided_study_session', name: 'Guided Path', desc: 'Step-by-step learning loop' },
                    { id: 'teach_me', name: 'Teach Me', desc: 'Conversational teacher explanation' },
                    { id: 'quiz_me', name: 'Quiz Me', desc: 'Test your understanding' },
                    { id: 'review_weak_topics', name: 'Review Weak', desc: 'Focus on failed concepts' }
                  ].map(m => (
                    <div 
                      key={m.id}
                      onClick={() => setSelectedMode(m.id)}
                      className={`p-3 border cursor-pointer select-none text-left flex flex-col justify-between h-20 transition-all ${
                        selectedMode === m.id 
                          ? 'border-slate-900 bg-white/40 shadow-sm' 
                          : 'border-slate-350 hover:bg-white/10'
                      }`}
                      style={{ borderRadius: 'var(--radius-control)' }}
                    >
                      <div className="flex items-center justify-between">
                        <span className="font-mono text-xs font-bold uppercase tracking-wider text-slate-900">{m.name}</span>
                        {selectedMode === m.id && <Check className="h-3 w-3 text-slate-900" />}
                      </div>
                      <span className="text-[10px] text-slate-500 font-body">{m.desc}</span>
                    </div>
                  ))}
                </div>
              </div>

              {/* Step 2: Difficulty */}
              <div className="space-y-2">
                <span className="block font-mono text-xs font-bold text-slate-800 uppercase tracking-wider">2. Choose Difficulty</span>
                <div className="flex gap-2">
                  {['easy', 'medium', 'hard'].map(d => (
                    <button
                      key={d}
                      type="button"
                      onClick={() => setSelectedDifficulty(d)}
                      className={`flex-1 h-9 font-mono text-xs font-semibold uppercase tracking-wider border transition-colors ${
                        selectedDifficulty === d
                          ? 'bg-slate-900 text-white border-slate-900'
                          : 'bg-transparent text-slate-700 border-slate-350 hover:bg-slate-100'
                      }`}
                      style={{ borderRadius: 'var(--radius-control)' }}
                    >
                      {d}
                    </button>
                  ))}
                </div>
              </div>

              {/* Step 3: Topics selection */}
              <div className="space-y-2">
                <span className="block font-mono text-xs font-bold text-slate-800 uppercase tracking-wider">3. Select Study Scope (Topics)</span>
                <div className="border border-slate-350 p-2 max-h-40 overflow-y-auto space-y-2 bg-white/40" style={{ borderRadius: 'var(--radius-control)' }}>
                  {topicsData?.topics.map(t => (
                    <div 
                      key={t.id}
                      onClick={() => toggleTopicSelect(t.id)}
                      className="flex items-center gap-2 p-2 hover:bg-white/20 cursor-pointer select-none text-xs"
                    >
                      <div className={`h-4 w-4 border flex items-center justify-center ${selectedTopics.includes(t.id) ? 'bg-slate-900 border-slate-900 text-white' : 'border-slate-400 bg-white'}`}>
                        {selectedTopics.includes(t.id) && <Check className="h-3 w-3" />}
                      </div>
                      <span className="font-body text-slate-850 font-bold">{t.name}</span>
                    </div>
                  ))}
                </div>
              </div>

              {/* Footer */}
              <div className="flex justify-end gap-2 pt-4 border-t border-slate-900/10">
                <Button 
                  variant="ghost" 
                  type="button" 
                  onClick={() => setIsConfigOpen(false)}
                  disabled={isStartingSession}
                >
                  Cancel
                </Button>
                <Button 
                  variant="primary" 
                  type="submit" 
                  loading={isStartingSession}
                >
                  Start Session
                </Button>
              </div>
            </form>
          </Card>
        </div>
      )}

      {/* Delete Confirmation Dialog */}
      <Modal
        isOpen={isDeleteOpen}
        onClose={() => !isDeleting && setIsDeleteOpen(false)}
        title="Delete Material?"
      >
        <div className="space-y-6">
          <p className="text-sm text-slate-650 font-body">
            This will permanently remove <span className="font-semibold text-slate-900">"{material.title}"</span> and its uploaded file. This action cannot be undone.
          </p>
          <div className="flex justify-end gap-2 border-t border-white/20 pt-4">
            <Button
              variant="ghost"
              type="button"
              onClick={() => setIsDeleteOpen(false)}
              disabled={isDeleting}
            >
              Cancel
            </Button>
            <Button
              variant="danger"
              type="button"
              loading={isDeleting}
              onClick={handleDeleteMaterial}
            >
              {isDeleting ? 'Deleting…' : 'Delete'}
            </Button>
          </div>
        </div>
      </Modal>
    </div>
  );
}
