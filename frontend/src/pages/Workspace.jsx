import React, { useState, useEffect, useRef } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { useAppStore } from '../store/appStore';
import { materialService, studySessionService, quizService } from '../services/api';
import { Card, Button, Badge } from '../components/Shared';
import MarkdownContent from '../components/MarkdownContent';
import { formatPercentage } from '../utils/format';
import { 
  BookOpen, 
  HelpCircle, 
  MessageSquare, 
  Award, 
  ChevronDown, 
  ChevronRight, 
  Send, 
  Lightbulb, 
  AlertCircle, 
  CheckCircle,
  XCircle,
  GraduationCap,
  Loader2
} from 'lucide-react';

export default function Workspace() {
  const { sessionId } = useParams();
  const navigate = useNavigate();
  const { addToast } = useAppStore();

  const [session, setSession] = useState(null);
  const [material, setMaterial] = useState(null);
  const [topicsData, setTopicsData] = useState(null);
  const [isLoading, setIsLoading] = useState(true);

  // Layout & Sidebar
  const [expandedTopics, setExpandedTopics] = useState([]);
  const [selectedSubtopicId, setSelectedSubtopicId] = useState(null);

  // Teach Me mode state
  const [teachChat, setTeachChat] = useState([]);
  const [teachInput, setTeachInput] = useState('');
  const [isTeachGenerating, setIsTeachGenerating] = useState(false);

  // Quiz Me mode state
  const [currentQuiz, setCurrentQuiz] = useState(null);
  const [currentQuestionIndex, setCurrentQuestionIndex] = useState(0);
  const [quizAnswerVerdict, setQuizAnswerVerdict] = useState(null); // { is_correct, ai_feedback }
  const [selectedAnswerOption, setSelectedAnswerOption] = useState('');
  const [isSubmittingAnswer, setIsSubmittingAnswer] = useState(false);
  const [isGeneratingQuiz, setIsGeneratingQuiz] = useState(false);
  const [isNextQuestionLoading, setIsNextQuestionLoading] = useState(false);

  // Guided Session flow state
  // Stages: 0 = Learn (Teach Me), 1 = Check Understanding (Quiz question), 2 = Evaluate (results), 3 = Review (Review Weak Topics if any)
  const [guidedStage, setGuidedStage] = useState(0);

  const messagesEndRef = useRef(null);

  // Load Session and Material data
  const loadWorkspaceData = async () => {
    try {
      const sess = await studySessionService.get(sessionId);
      setSession(sess);
      
      const mat = await materialService.get(sess.material_id);
      setMaterial(mat);

      const top = await materialService.getTopics(sess.material_id);
      setTopicsData(top);
      
      // Auto expand first topic
      if (top && top.topics.length > 0) {
        setExpandedTopics([top.topics[0].id]);
        // Auto select first subtopic
        if (top.topics[0].subtopics.length > 0 && !selectedSubtopicId) {
          setSelectedSubtopicId(top.topics[0].subtopics[0].id);
        }
      }
    } catch (err) {
      addToast('Error loading workspace session', 'error');
      navigate('/materials');
    } finally {
      setIsLoading(false);
    }
  };

  useEffect(() => {
    loadWorkspaceData();
  }, [sessionId]);

  // Scroll teach chat
  useEffect(() => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  }, [teachChat, isTeachGenerating]);

  // Handle subtopic sidebar click
  const handleSubtopicClick = async (subtopicId) => {
    setSelectedSubtopicId(subtopicId);
    
    // Reset views
    setTeachChat([]);
    setCurrentQuiz(null);
    setQuizAnswerVerdict(null);
    setSelectedAnswerOption('');
    
    if (session.mode === 'teach_me') {
      triggerExplanation(subtopicId, 'explain');
    } else if (session.mode === 'review_weak_topics') {
      triggerExplanation(subtopicId, 'review');
    } else if (session.mode === 'quiz_me') {
      triggerQuizStart(subtopicId);
    } else if (session.mode === 'guided_study_session') {
      setGuidedStage(0);
      triggerExplanation(subtopicId, 'explain');
    }
  };

  // Trigger conversational explanation
  const triggerExplanation = async (subtopicId, intent, userMessage = '') => {
    setIsTeachGenerating(true);
    if (userMessage) {
      setTeachChat(prev => [...prev, { sender: 'user', text: userMessage }]);
    }
    
    try {
      const res = await studySessionService.getExplanation(session.id, subtopicId, intent, userMessage);
      setTeachChat(prev => [...prev, { sender: 'ai', text: res.explanation }]);
    } catch (err) {
      addToast('Failed to generate explanation', 'error');
    } finally {
      setIsTeachGenerating(false);
    }
  };

  // Trigger quiz starting
  const triggerQuizStart = async (subtopicId) => {
    setIsGeneratingQuiz(true);
    try {
      // Find parent topic
      let topicId = null;
      topicsData.topics.forEach(t => {
        if (t.subtopics.find(s => s.id === subtopicId)) topicId = t.id;
      });

      const quiz = await quizService.create(session.id, topicId, subtopicId, session.difficulty, 3);
      setCurrentQuiz(quiz);
      setCurrentQuestionIndex(0);
      setQuizAnswerVerdict(null);
      setSelectedAnswerOption('');
    } catch (err) {
      console.error('Failed to generate quiz:', err);
      addToast('Failed to generate quiz', 'error');
    } finally {
      setIsGeneratingQuiz(false);
    }
  };

  // Submit quiz answer
  const handleSubmitAnswer = async () => {
    if (!selectedAnswerOption) {
      addToast('Please select or type an answer', 'warning');
      return;
    }

    const question = currentQuiz?.questions?.[currentQuestionIndex];
    if (!question) {
      addToast('Question is not available', 'error');
      return;
    }

    setIsSubmittingAnswer(true);
    try {
      const res = await quizService.submitAnswer(currentQuiz.id, question.id, selectedAnswerOption);
      setQuizAnswerVerdict({
        is_correct: res.is_correct,
        ai_feedback: res.ai_feedback,
        quiz_status: res.quiz_status,
        quiz_result: res.quiz_result
      });

      // Reload sidebar progress dynamically
      const updatedTopics = await materialService.getTopics(session.material_id);
      setTopicsData(updatedTopics);
    } catch (err) {
      console.error('Failed to submit answer:', err);
      addToast(err.message || 'Error submitting answer', 'error');
    } finally {
      setIsSubmittingAnswer(false);
    }
  };

  // Advance to the next question, or load the final results summary.
  // Questions are generated up-front as a full array, so moving to the next
  // question is synchronous; the border index is guarded so it can never
  // run past the loaded array (which would previously render a blank screen).
  const handleNextQuizQuestion = async () => {
    const questions = currentQuiz?.questions || [];
    const isLast = currentQuestionIndex + 1 >= questions.length;
    const wasCompleted = quizAnswerVerdict?.quiz_status === 'completed';
    const quizDone = wasCompleted || isLast;

    if (!quizDone) {
      setCurrentQuestionIndex((prev) => prev + 1);
      setQuizAnswerVerdict(null);
      setSelectedAnswerOption('');
      return;
    }

    // Last question answered - load the completed results summary.
    setIsNextQuestionLoading(true);
    try {
      const q = await quizService.get(currentQuiz.id);
      setCurrentQuiz(q);
    } catch (err) {
      console.error('Failed to load quiz results:', err);
      addToast(err.message || 'Failed to load quiz results', 'error');
    } finally {
      setIsNextQuestionLoading(false);
    }

    if (session.mode === 'guided_study_session' && wasCompleted) {
      setGuidedStage(2); // Go to Evaluate results summary
    } else if (wasCompleted) {
      // quiz_me: clear the verdict so the completion screen replaces the question.
      setQuizAnswerVerdict(null);
      setSelectedAnswerOption('');
    }
  };

  // Trigger completion of entire session
  const handleCompleteSession = async () => {
    try {
      await studySessionService.complete(session.id);
      addToast('Study session completed and saved!', 'success');
      navigate(`/materials/${material.id}`);
    } catch (err) {
      navigate(`/materials/${material.id}`);
    }
  };

  const toggleTopicExpand = (topicId) => {
    setExpandedTopics(prev => 
      prev.includes(topicId) 
        ? prev.filter(id => id !== topicId) 
        : [...prev, topicId]
    );
  };

  // Guided flow transitions
  const startGuidedTest = () => {
    setGuidedStage(1);
    triggerQuizStart(selectedSubtopicId);
  };

  const startGuidedReview = () => {
    setGuidedStage(3);
    triggerExplanation(selectedSubtopicId, 'review');
  };

  if (isLoading) {
    return (
      <div className="flex h-[75vh] items-center justify-center">
        <Loader2 className="animate-spin h-10 w-10 text-slate-800" />
      </div>
    );
  }

  // Find active subtopic label and status
  let activeSubtopic = null;
  topicsData?.topics.forEach(t => {
    const s = t.subtopics.find(sub => sub.id === selectedSubtopicId);
    if (s) activeSubtopic = s;
  });

  // Quiz phase / completion gates used by the CHECK UNDERSTANDING renderer.
  // Splitting the "phase" from "quiz exists" ensures the loading/error states
  // render while the AI generation is still in flight (no blank regions).
  const inQuizPhase = session.mode === 'quiz_me' || (session.mode === 'guided_study_session' && guidedStage === 1);
  const quizCompletedViewed = session.mode === 'quiz_me' && currentQuiz?.status === 'completed';
  const currentQuestion = currentQuiz?.questions?.[currentQuestionIndex];

  const sidebarStatusIcon = (status) => {
    if (status === 'mastered') return <span className="text-emerald-600 font-bold font-mono">✓</span>;
    if (status === 'needs_review') return <span className="text-red-500 font-bold font-mono">⚠</span>;
    if (status === 'in_progress') return <span className="text-amber-500 font-bold font-mono">◐</span>;
    return <span className="text-slate-400 font-bold font-mono">○</span>;
  };

  return (
    <div className="grid grid-cols-1 lg:grid-cols-4 gap-8 items-start h-[calc(100vh-140px)]">
      {/* 1. Left Sidebar / Learning Map (1 Column) */}
      <Card glass className="lg:col-span-1 p-4 border-slate-900/10 h-full flex flex-col justify-between overflow-y-auto">
        <div className="space-y-6">
          {/* Overall Mastery Ring widget */}
          <div className="flex items-center justify-between pb-4 border-b border-slate-900/10">
            <div>
              <h2 className="font-display font-bold text-sm text-slate-900 leading-tight">Overall Mastery</h2>
              <span className="font-mono text-[9px] text-slate-500 uppercase tracking-wider">{material.title}</span>
            </div>
            {topicsData && (
              <div className="relative flex items-center justify-center" style={{ width: 50, height: 50 }}>
                <svg className="w-full h-full transform -rotate-90">
                  <circle
                    className="text-slate-200/50"
                    strokeWidth="4"
                    stroke="currentColor"
                    fill="transparent"
                    r="20"
                    cx="25"
                    cy="25"
                  />
                  <circle
                    className="text-slate-900"
                    strokeWidth="4"
                    strokeDasharray={2 * Math.PI * 20}
                    strokeDashoffset={2 * Math.PI * 20 - (topicsData.overall_mastery / 100) * 2 * Math.PI * 20}
                    strokeLinecap="round"
                    stroke="currentColor"
                    fill="transparent"
                    r="20"
                    cx="25"
                    cy="25"
                  />
                </svg>
                <span className="absolute font-mono text-[10px] font-bold text-slate-800">
                  {formatPercentage(topicsData.overall_mastery)}%
                </span>
              </div>
            )}
          </div>

          {/* Collapsible Topics & Subtopics Accordion */}
          <div className="space-y-2">
            <h3 className="font-mono text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-2">Learning Map</h3>
            <div className="space-y-2">
              {topicsData?.topics.map((t) => (
                <div key={t.id} className="space-y-1">
                  <button 
                    onClick={() => toggleTopicExpand(t.id)}
                    className="w-full flex items-center justify-between p-2 hover:bg-white/20 text-left"
                    style={{ borderRadius: 'var(--radius-control)' }}
                  >
                    <span className="font-display font-semibold text-xs text-slate-900 line-clamp-1">
                      {t.name}
                    </span>
                    {expandedTopics.includes(t.id) ? <ChevronDown className="h-3 w-3 text-slate-500" /> : <ChevronRight className="h-3 w-3 text-slate-500" />}
                  </button>

                  {expandedTopics.includes(t.id) && (
                    <div className="pl-3 border-l border-slate-300/60 space-y-1 mt-1">
                      {t.subtopics.map(sub => (
                        <button
                          key={sub.id}
                          onClick={() => handleSubtopicClick(sub.id)}
                          className={`w-full flex items-center justify-between p-1.5 text-left text-xs transition-colors ${
                            selectedSubtopicId === sub.id 
                              ? 'bg-slate-900/10 text-slate-900 font-bold border-r-2 border-slate-900' 
                              : 'text-slate-600 hover:text-slate-900 hover:bg-white/10'
                          }`}
                        >
                          <div className="flex items-center gap-1.5 truncate">
                            {sidebarStatusIcon(sub.status)}
                            <span className="truncate">{sub.name}</span>
                          </div>
                          <span className="font-mono text-[9px] text-slate-500">{sub.mastery_score}%</span>
                        </button>
                      ))}
                    </div>
                  )}
                </div>
              ))}
            </div>
          </div>
        </div>

        <div className="pt-4 border-t border-slate-900/10">
          <Button variant="danger" onClick={handleCompleteSession} className="w-full text-center h-9 text-[10px]">
            Leave Workspace
          </Button>
        </div>
      </Card>

      {/* 2. Right Workspace Learning Pane (3 Columns) */}
      <div className="lg:col-span-3 flex flex-col h-full space-y-4">
        {/* Active Subtopic Header */}
        <Card glass className="p-4 border-slate-900/10 flex items-center justify-between">
          <div>
            <span className="font-mono text-[9px] text-slate-500 uppercase tracking-wider">Active Concept</span>
            <h1 className="text-xl font-bold font-display text-slate-900 leading-tight">
              {activeSubtopic ? activeSubtopic.name : 'Select a subtopic'}
            </h1>
            <p className="text-xs text-slate-600 font-body line-clamp-1">
              {activeSubtopic ? activeSubtopic.description : ''}
            </p>
          </div>

          <div className="flex items-center gap-4">
            <div className="text-right">
              <span className="block font-mono text-[9px] text-slate-500 uppercase tracking-wider">Current Mastery</span>
              <span className="font-mono text-xs font-bold text-slate-900">{activeSubtopic ? activeSubtopic.mastery_score : 0}%</span>
            </div>
            {activeSubtopic && (
              <Badge variant={
                activeSubtopic.status === 'mastered' ? 'success' :
                activeSubtopic.status === 'needs_review' ? 'error' :
                activeSubtopic.status === 'in_progress' ? 'warning' : 'neutral'
              }>
                {activeSubtopic.status.replace('_', ' ')}
              </Badge>
            )}
          </div>
        </Card>

        {/* Guided Stage Indicator */}
        {session.mode === 'guided_study_session' && (
          <div className="flex gap-2 font-mono text-[10px] font-bold uppercase tracking-wider text-slate-650 bg-white/40 p-2 border border-slate-900/5 justify-between">
            {['1. Learn', '2. Check Understanding', '3. Evaluation', '4. Review'].map((st, i) => (
              <div 
                key={st}
                className={`px-3 py-1 transition-all ${
                  guidedStage === i 
                    ? 'text-slate-900 border-b-2 border-slate-900 font-black' 
                    : guidedStage > i 
                    ? 'text-slate-400 line-through' 
                    : 'text-slate-400'
                }`}
              >
                {st}
              </div>
            ))}
          </div>
        )}

        {/* Main Work Pane */}
        <Card glass className="flex-1 p-6 border-slate-900/10 flex flex-col justify-between overflow-y-auto min-h-[300px]">
          
          {/* TEACH ME (OR GUIDED LEARN / REVIEW STAGES) */}
          {(session.mode === 'teach_me' || session.mode === 'review_weak_topics' || 
            (session.mode === 'guided_study_session' && (guidedStage === 0 || guidedStage === 3))) ? (
            <div className="flex-1 flex flex-col justify-between h-full">
              {/* Chat Thread Container */}
              <div className="flex-1 overflow-y-auto space-y-4 pr-1 mb-6 max-h-[420px]">
                {teachChat.length === 0 && !isTeachGenerating ? (
                  <div className="text-center py-12 text-slate-500 font-body">
                    <MessageSquare className="h-10 w-10 mx-auto mb-2 text-slate-400" />
                    Click a subtopic in the sidebar learning map to load the initial analysis context.
                  </div>
                ) : (
                  teachChat.map((msg, i) => (
                    <div 
                      key={i} 
                      className={`flex ${msg.sender === 'user' ? 'justify-end' : 'justify-start'}`}
                    >
                      <div 
                        className={`p-4 max-w-xl min-w-0 text-sm font-body leading-relaxed border ${
                          msg.sender === 'user' 
                            ? 'bg-slate-900 text-white border-slate-900' 
                            : 'bg-white/90 text-slate-900 border-slate-200 shadow-sm'
                        }`}
                        style={{ borderRadius: 'var(--radius-glass)' }}
                      >
                        {msg.sender === 'user' ? (
                          <div className="whitespace-pre-wrap break-words">{msg.text}</div>
                        ) : (
                          <MarkdownContent>{msg.text}</MarkdownContent>
                        )}
                      </div>
                    </div>
                  ))
                )}
                {isTeachGenerating && (
                  <div className="flex justify-start">
                    <div className="bg-white/90 border border-slate-200 p-4 max-w-xl rounded-lg shadow-sm flex items-center gap-2">
                      <div className="flex gap-1">
                        <span className="h-2 w-2 bg-slate-900 rounded-full animate-bounce" style={{ animationDelay: '0ms' }} />
                        <span className="h-2 w-2 bg-slate-900 rounded-full animate-bounce" style={{ animationDelay: '150ms' }} />
                        <span className="h-2 w-2 bg-slate-900 rounded-full animate-bounce" style={{ animationDelay: '300ms' }} />
                      </div>
                      <span className="font-mono text-[10px] text-slate-500 uppercase tracking-wider font-bold">AI Teacher Thinking...</span>
                    </div>
                  </div>
                )}
                <div ref={messagesEndRef} />
              </div>

              {/* Chat action controls */}
              <div className="border-t border-slate-900/10 pt-4 space-y-4">
                {/* Follow up suggestions */}
                {teachChat.length > 0 && !isTeachGenerating && (
                  <div className="flex flex-wrap gap-2">
                    <button 
                      onClick={() => triggerExplanation(selectedSubtopicId, 'simplify')}
                      className="h-8 px-3 border border-slate-350 hover:bg-slate-100 font-mono text-[10px] font-semibold uppercase tracking-wider text-slate-800"
                      style={{ borderRadius: 'var(--radius-control)' }}
                    >
                      <Lightbulb className="h-3.5 w-3.5 inline mr-1 text-amber-500" /> Explain Simpler
                    </button>
                    <button 
                      onClick={() => triggerExplanation(selectedSubtopicId, 'example')}
                      className="h-8 px-3 border border-slate-350 hover:bg-slate-100 font-mono text-[10px] font-semibold uppercase tracking-wider text-slate-800"
                      style={{ borderRadius: 'var(--radius-control)' }}
                    >
                      <GraduationCap className="h-3.5 w-3.5 inline mr-1 text-blue-500" /> Give Example
                    </button>
                    {session.mode === 'guided_study_session' && guidedStage === 0 && (
                      <Button variant="primary" onClick={startGuidedTest} className="h-8 px-4 text-[10px]">
                        Start Understanding Check &rarr;
                      </Button>
                    )}
                    {session.mode === 'guided_study_session' && guidedStage === 3 && (
                      <Button variant="primary" onClick={handleCompleteSession} className="h-8 px-4 text-[10px]">
                        Finish Study Session
                      </Button>
                    )}
                  </div>
                )}

                {/* Free form input box */}
                <form 
                  onSubmit={(e) => {
                    e.preventDefault();
                    if (!teachInput.trim()) return;
                    triggerExplanation(selectedSubtopicId, 'explain', teachInput);
                    setTeachInput('');
                  }}
                  className="flex gap-2 items-center"
                >
                  <input
                    type="text"
                    placeholder="Ask your AI Teacher a follow-up question..."
                    value={teachInput}
                    disabled={isTeachGenerating}
                    onChange={(e) => setTeachInput(e.target.value)}
                    className="flex-1 h-10 px-3 bg-white/60 border border-slate-300 font-body text-sm text-slate-900 focus:outline-none focus:border-slate-850 focus:ring-1 focus:ring-slate-850"
                    style={{ borderRadius: 'var(--radius-control)' }}
                  />
                  <Button variant="primary" type="submit" disabled={isTeachGenerating} className="h-10 px-4">
                    <Send className="h-4 w-4" />
                  </Button>
                </form>
              </div>
            </div>
          ) : null}

{/* QUIZ ME / CHECK UNDERSTANDING FLOW */}
          {inQuizPhase && !quizCompletedViewed ? (
            <div className="flex-1 flex flex-col justify-between h-full">

              {/* Quiz Wizard Content */}
              {isGeneratingQuiz || isNextQuestionLoading ? (
                <div className="flex-1 flex flex-col items-center justify-center py-12 space-y-3 text-center">
                  <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-slate-900" />
                  <span className="font-mono text-xs font-semibold uppercase text-slate-600">
                    {isNextQuestionLoading ? 'Preparing your results...' : 'Preparing your question...'}
                  </span>
                  <p className="font-body text-xs text-slate-500 max-w-xs">
                    AI is preparing content based on your material.
                  </p>
                </div>
              ) : !currentQuiz ? (
                <div className="flex-1 flex flex-col items-center justify-center py-12 space-y-4 text-center">
                  <AlertCircle className="h-10 w-10 text-red-600 mx-auto" />
                  <p className="font-body text-sm text-slate-700">
                    We couldn't prepare the questions. Please try again.
                  </p>
                  <Button variant="primary" onClick={() => triggerQuizStart(selectedSubtopicId)}>
                    Retry
                  </Button>
                </div>
              ) : currentQuiz.questions.length === 0 ? (
                <div className="flex-1 flex flex-col items-center justify-center py-12 space-y-4 text-center">
                  <p className="font-body text-sm text-slate-500">No questions could be generated.</p>
                  <Button variant="primary" onClick={() => triggerQuizStart(selectedSubtopicId)}>
                    Retry
                  </Button>
                </div>
              ) : currentQuestion ? (
                <div className="space-y-6">
                  {/* Progress info */}
                  <div className="flex justify-between items-center border-b border-slate-900/5 pb-3">
                    <span className="font-mono text-[10px] font-bold text-slate-500 uppercase tracking-wider">
                      Question {currentQuestionIndex + 1} of {currentQuiz.total_questions}
                    </span>
                    <Badge variant="warning">{session.difficulty}</Badge>
                  </div>

                  {/* Question Stem */}
                  <div className="space-y-4">
                    <h2 className="text-lg font-bold font-display text-slate-900">
                      {currentQuestion.question_text.replace(/^#{1,6}\s+/, '')}
                    </h2>

                    {/* Answer controls based on question type */}
                    {currentQuestion.question_type === 'true_false' ? (
                      <div className="grid grid-cols-2 gap-3">
                        {['True', 'False'].map((choice) => {
                          const isSelected = selectedAnswerOption === choice;
                          const isAnswered = quizAnswerVerdict !== null;

                          return (
                            <div
                              key={choice}
                              onClick={() => !isAnswered && setSelectedAnswerOption(choice)}
                              className={`p-3 border cursor-pointer flex items-center justify-center text-sm font-semibold transition-all ${
                                isSelected
                                  ? 'border-slate-900 bg-slate-900/5'
                                  : 'border-slate-350 hover:bg-white/10'
                              } ${isAnswered ? 'opacity-85 pointer-events-none' : ''}`}
                              style={{ borderRadius: 'var(--radius-control)' }}
                            >
                              {choice}
                            </div>
                          );
                        })}
                      </div>
                    ) : currentQuestion.question_type === 'short_answer' ? (
                      <div className="space-y-1">
                        <textarea
                          value={selectedAnswerOption}
                          disabled={quizAnswerVerdict !== null}
                          onChange={(e) => setSelectedAnswerOption(e.target.value)}
                          placeholder="Type your answer..."
                          rows={3}
                          className="w-full p-3 bg-white/60 border border-slate-300 font-body text-sm text-slate-900 focus:outline-none focus:border-slate-850 focus:ring-1 focus:ring-slate-850 disabled:opacity-85"
                          style={{ borderRadius: 'var(--radius-control)' }}
                        />
                        <span className="font-mono text-[9px] text-slate-500 uppercase tracking-wider">
                          Type your answer then press Submit
                        </span>
                      </div>
                    ) : (
                      <div className="space-y-3">
                        {(currentQuestion.options || []).map((opt) => {
                          const isSelected = selectedAnswerOption === opt;
                          const isAnswered = quizAnswerVerdict !== null;

                          return (
                            <div
                              key={opt}
                              onClick={() => !isAnswered && setSelectedAnswerOption(opt)}
                              className={`p-3 border cursor-pointer flex items-center justify-between text-sm transition-all ${
                                isSelected
                                  ? 'border-slate-900 bg-slate-900/5 font-semibold'
                                  : 'border-slate-350 hover:bg-white/10'
                              } ${isAnswered ? 'opacity-85 pointer-events-none' : ''}`}
                              style={{ borderRadius: 'var(--radius-control)' }}
                            >
                              <span className="font-body text-slate-800">{opt}</span>
                              <div className={`h-4 w-4 rounded-full border flex items-center justify-center ${isSelected ? 'bg-slate-900 border-slate-900 text-white' : 'border-slate-400 bg-white'}`}>
                                {isSelected && <span className="h-2 w-2 bg-white rounded-full" />}
                              </div>
                            </div>
                          );
                        })}
                      </div>
                    )}
                  </div>

                  {/* Grading Result feedback */}
                  {quizAnswerVerdict && (
                    <div className={`p-4 border font-body text-sm leading-relaxed flex items-start gap-3 animate-in fade-in duration-200 ${
                      quizAnswerVerdict.is_correct
                        ? 'bg-emerald-50 text-emerald-800 border-emerald-200'
                        : 'bg-red-50 text-red-800 border-red-200'
                    }`} style={{ borderRadius: 'var(--radius-glass)' }}>
                      {quizAnswerVerdict.is_correct
                        ? <CheckCircle className="h-5 w-5 text-emerald-600 flex-shrink-0 mt-0.5" />
                        : <XCircle className="h-5 w-5 text-red-650 flex-shrink-0 mt-0.5" />}
                      <div>
                        <span className="font-display font-semibold block uppercase text-xs tracking-wide mb-1">
                          {quizAnswerVerdict.is_correct ? 'Correct' : 'Incorrect'}
                        </span>
                        <MarkdownContent>{quizAnswerVerdict.ai_feedback}</MarkdownContent>
                      </div>
                    </div>
                  )}
                </div>
              ) : (
                <div className="flex-1 flex flex-col items-center justify-center py-12 space-y-4 text-center">
                  <AlertCircle className="h-10 w-10 text-red-600 mx-auto" />
                  <p className="font-body text-sm text-slate-700">
                    Question not available. Please try again.
                  </p>
                  <Button variant="primary" onClick={() => triggerQuizStart(selectedSubtopicId)}>
                    Retry
                  </Button>
                </div>
              )}

              {/* Quiz Submit/Next Controls - only shown when a question is on screen */}
              {currentQuiz && currentQuiz.questions.length > 0 && currentQuestion && (
                <div className="border-t border-slate-900/10 pt-4 flex justify-end">
                  {quizAnswerVerdict ? (
                    <Button
                      variant="primary"
                      onClick={handleNextQuizQuestion}
                      loading={isNextQuestionLoading}
                    >
                      {quizAnswerVerdict.quiz_status === 'completed' ? 'View Results →' : 'Next Question →'}
                    </Button>
                  ) : (
                    <Button
                      variant="primary"
                      onClick={handleSubmitAnswer}
                      loading={isSubmittingAnswer}
                    >
                      Submit Answer
                    </Button>
                  )}
                </div>
              )}
            </div>
          ) : null}

          {/* QUIZ COMPLETION / EVALUATION RESULTS SCREEN */}
          {(guidedStage === 2 || quizCompletedViewed) && (
            <div className="text-center py-8 space-y-6 flex flex-col justify-between h-full">
              <div className="space-y-4">
                <div className="h-16 w-16 bg-slate-900/5 text-slate-900 rounded-full flex items-center justify-center mx-auto">
                  <Award className="h-8 w-8" />
                </div>
                <h2 className="text-2xl font-bold font-display text-slate-900">Quiz Completed!</h2>
                
                {/* Score display card */}
                <div className="max-w-xs mx-auto p-4 border border-slate-350 bg-white/40" style={{ borderRadius: 'var(--radius-glass)' }}>
                  <span className="block font-mono text-[9px] text-slate-500 uppercase tracking-wider">Your Score</span>
                  <span className="font-mono text-4xl font-bold text-slate-900">{currentQuiz?.score}%</span>
                  <p className="font-body text-xs text-slate-500 mt-2">
                    Correct Answers: {currentQuiz?.correct_count} of {currentQuiz?.total_questions}
                  </p>
                </div>
              </div>

              {/* Topic performance summary */}
              {currentQuiz?.topic_performance && currentQuiz.topic_performance.length > 0 && (
                <div className="max-w-md mx-auto w-full text-left space-y-2">
                  <span className="block font-mono text-[9px] text-slate-500 uppercase tracking-wider text-center">Topic Performance</span>
                  <div className="border border-slate-350 bg-white/40 p-3 space-y-2" style={{ borderRadius: 'var(--radius-control)' }}>
                    {currentQuiz.topic_performance.map(perf => (
                      <div key={perf.subtopic_id} className="flex justify-between items-center text-xs">
                        <span className="font-body text-slate-800">{perf.subtopic_name}</span>
                        <div className="flex gap-2">
                          <span className="font-mono text-slate-500">{perf.mastery_score}%</span>
                          <Badge variant={perf.status === 'mastered' ? 'success' : perf.status === 'needs_review' ? 'error' : 'warning'}>
                            {perf.status.replace('_', ' ')}
                          </Badge>
                        </div>
                      </div>
                    ))}
                  </div>
                </div>
              )}

              {/* Guided loop controls / Completion links */}
              <div className="border-t border-slate-900/10 pt-4 flex gap-3 justify-center">
                {session.mode === 'guided_study_session' ? (
                  <>
                    <Button variant="secondary" onClick={startGuidedReview}>
                      Review Weak Areas &rarr;
                    </Button>
                    <Button variant="primary" onClick={handleCompleteSession}>
                      Finish Session
                    </Button>
                  </>
                ) : (
                  <>
                    <Button variant="secondary" onClick={() => triggerQuizStart(selectedSubtopicId)}>
                      Try Quiz Again
                    </Button>
                    <Button variant="primary" onClick={handleCompleteSession}>
                      Leave Workspace
                    </Button>
                  </>
                )}
              </div>
            </div>
          )}

          {/* Fallback starting empty state if no subtopic active */}
          {!selectedSubtopicId && (
            <div className="text-center py-16 space-y-4">
              <GraduationCap className="h-16 w-16 text-slate-400 mx-auto" />
              <h3 className="text-xl font-bold font-display text-slate-900">Select a subtopic to study</h3>
              <p className="text-sm text-slate-650 font-body max-w-sm mx-auto">
                Explore the learning tree outline on the left menu, select any concept subtopic to get personalized tutoring or quizzes!
              </p>
            </div>
          )}
          
        </Card>
      </div>
    </div>
  );
}
