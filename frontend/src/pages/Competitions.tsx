import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { competitionsApi, performersApi, festivalsApi } from '@/api/endpoints';
import type { Competition, BracketData } from '@/api/endpoints';
import { useToast } from '@/components/common/Toast';
import { Modal } from '@/components/common/Modal';
import { Plus, Trophy, Play, CheckCircle, Users, ChevronRight } from 'lucide-react';

const STATUS_COLORS: Record<string, string> = {
  setup: 'bg-gray-100 text-gray-800',
  registration: 'bg-blue-100 text-blue-800',
  seeding: 'bg-purple-100 text-purple-800',
  active: 'bg-green-100 text-green-800',
  paused: 'bg-yellow-100 text-yellow-800',
  completed: 'bg-gray-200 text-gray-700',
  cancelled: 'bg-red-100 text-red-800',
};

const TYPE_LABELS: Record<string, string> = {
  single_elimination: 'Single Elimination',
  double_elimination: 'Double Elimination',
  round_robin: 'Round Robin',
};

export function Competitions() {
  const [selectedCompetition, setSelectedCompetition] = useState<number | null>(null);
  const [showCreateModal, setShowCreateModal] = useState(false);
  const [showBracketModal, setShowBracketModal] = useState(false);
  const [selectedPerformers, setSelectedPerformers] = useState<number[]>([]);
  const queryClient = useQueryClient();
  const { addToast } = useToast();

  const { data: festivals = [] } = useQuery({
    queryKey: ['festivals'],
    queryFn: () => festivalsApi.getAll(),
  });

  const { data: competitions = [], isLoading } = useQuery({
    queryKey: ['competitions'],
    queryFn: () => competitionsApi.getAll(),
  });

  const { data: performers = [] } = useQuery({
    queryKey: ['performers', 'accepted'],
    queryFn: () => performersApi.getAll({ application_status: 'accepted' }),
  });

  const { data: bracketData } = useQuery({
    queryKey: ['bracket', selectedCompetition],
    queryFn: () => competitionsApi.getBracket(selectedCompetition!),
    enabled: !!selectedCompetition && showBracketModal,
  });

  const createMutation = useMutation({
    mutationFn: (data: Partial<Competition>) => competitionsApi.create(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['competitions'] });
      setShowCreateModal(false);
      addToast('success', 'Competition created successfully');
    },
    onError: (error: Error) => {
      addToast('error', error.message);
    },
  });

  const generateBracketMutation = useMutation({
    mutationFn: ({ id, performerIds }: { id: number; performerIds: number[] }) =>
      competitionsApi.generateBracket(id, performerIds),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['competitions'] });
      queryClient.invalidateQueries({ queryKey: ['bracket'] });
      setSelectedPerformers([]);
      addToast('success', 'Bracket generated successfully');
    },
    onError: (error: Error) => {
      addToast('error', error.message);
    },
  });

  const startVotingMutation = useMutation({
    mutationFn: (matchId: number) => competitionsApi.startVoting(matchId, 10),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['bracket'] });
      addToast('success', 'Voting started');
    },
    onError: (error: Error) => {
      addToast('error', error.message);
    },
  });

  const completeMatchMutation = useMutation({
    mutationFn: (matchId: number) => competitionsApi.completeMatch(matchId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['bracket'] });
      queryClient.invalidateQueries({ queryKey: ['competitions'] });
      addToast('success', 'Match completed');
    },
    onError: (error: Error) => {
      addToast('error', error.message);
    },
  });

  const handleSubmit = (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    const formData = new FormData(e.currentTarget);
    createMutation.mutate({
      festival_id: parseInt(formData.get('festival_id') as string),
      name: formData.get('name') as string,
      description: formData.get('description') as string,
      competition_type: formData.get('competition_type') as Competition['competition_type'],
      voting_method: formData.get('voting_method') as Competition['voting_method'],
    });
  };

  const togglePerformerSelection = (id: number) => {
    setSelectedPerformers((prev) =>
      prev.includes(id) ? prev.filter((p) => p !== id) : [...prev, id]
    );
  };

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-900">Competitions</h1>
        <button className="btn btn-primary" onClick={() => setShowCreateModal(true)}>
          <Plus className="w-4 h-4" />
          New Competition
        </button>
      </div>

      {/* Competition List */}
      {isLoading ? (
        <div className="animate-pulse space-y-4">
          {[1, 2, 3].map((i) => (
            <div key={i} className="h-24 bg-gray-200 rounded-xl" />
          ))}
        </div>
      ) : competitions.length === 0 ? (
        <div className="card p-12 text-center">
          <Trophy className="w-12 h-12 mx-auto text-gray-400 mb-4" />
          <h3 className="text-lg font-medium text-gray-900 mb-2">No competitions yet</h3>
          <p className="text-gray-500 mb-4">Create your first competition to start bracket tournaments.</p>
          <button className="btn btn-primary" onClick={() => setShowCreateModal(true)}>
            <Plus className="w-4 h-4" />
            Create Competition
          </button>
        </div>
      ) : (
        <div className="grid gap-4">
          {competitions.map((competition) => (
            <div key={competition.id} className="card p-6">
              <div className="flex items-start justify-between">
                <div>
                  <div className="flex items-center gap-3 mb-2">
                    <h3 className="text-lg font-semibold">{competition.name}</h3>
                    <span className={`px-2 py-1 text-xs font-medium rounded-full ${STATUS_COLORS[competition.status]}`}>
                      {competition.status}
                    </span>
                  </div>
                  <p className="text-sm text-gray-500 mb-3">{competition.description}</p>
                  <div className="flex items-center gap-4 text-sm text-gray-600">
                    <span>{TYPE_LABELS[competition.competition_type]}</span>
                    <span className="text-gray-300">|</span>
                    <span>{competition.rounds_count} rounds</span>
                    {competition.winner_performer_id && (
                      <>
                        <span className="text-gray-300">|</span>
                        <span className="text-green-600 font-medium flex items-center gap-1">
                          <Trophy className="w-4 h-4" />
                          Winner declared
                        </span>
                      </>
                    )}
                  </div>
                </div>
                <div className="flex gap-2">
                  {competition.status === 'setup' && (
                    <button
                      className="btn btn-secondary btn-sm"
                      onClick={() => {
                        setSelectedCompetition(competition.id);
                        setShowBracketModal(true);
                      }}
                    >
                      <Users className="w-4 h-4" />
                      Setup Bracket
                    </button>
                  )}
                  {competition.status === 'active' && (
                    <button
                      className="btn btn-primary btn-sm"
                      onClick={() => {
                        setSelectedCompetition(competition.id);
                        setShowBracketModal(true);
                      }}
                    >
                      <ChevronRight className="w-4 h-4" />
                      View Bracket
                    </button>
                  )}
                  {competition.status === 'completed' && (
                    <button
                      className="btn btn-secondary btn-sm"
                      onClick={() => {
                        setSelectedCompetition(competition.id);
                        setShowBracketModal(true);
                      }}
                    >
                      <Trophy className="w-4 h-4" />
                      View Results
                    </button>
                  )}
                </div>
              </div>
            </div>
          ))}
        </div>
      )}

      {/* Create Competition Modal */}
      <Modal isOpen={showCreateModal} onClose={() => setShowCreateModal(false)} title="Create Competition">
        <form onSubmit={handleSubmit} className="space-y-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1" htmlFor="competitions-festival">Festival</label>
            <select id="competitions-festival" name="festival_id" className="input" required>
              <option value="">Select festival...</option>
              {festivals.map((f) => (
                <option key={f.id} value={f.id}>
                  {f.name}
                </option>
              ))}
            </select>
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1" htmlFor="competitions-name">Name</label>
            <input id="competitions-name" type="text" name="name" className="input" placeholder="Battle of the Bands" required />
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1" htmlFor="competitions-description">Description</label>
            <textarea id="competitions-description" name="description" className="input" rows={3} placeholder="Optional description..." />
          </div>
          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1" htmlFor="competitions-type">Type</label>
              <select id="competitions-type" name="competition_type" className="input" required>
                <option value="single_elimination">Single Elimination</option>
                <option value="double_elimination">Double Elimination</option>
                <option value="round_robin">Round Robin</option>
              </select>
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1" htmlFor="competitions-voting-method">Voting Method</label>
              <select id="competitions-voting-method" name="voting_method" className="input" required>
                <option value="head_to_head">Head to Head</option>
                <option value="borda">Borda Count</option>
                <option value="judges">Judges Only</option>
                <option value="combined">Combined</option>
              </select>
            </div>
          </div>
          <div className="flex justify-end gap-2 pt-4">
            <button type="button" className="btn btn-secondary" onClick={() => setShowCreateModal(false)}>
              Cancel
            </button>
            <button type="submit" className="btn btn-primary" disabled={createMutation.isPending}>
              {createMutation.isPending ? 'Creating...' : 'Create Competition'}
            </button>
          </div>
        </form>
      </Modal>

      {/* Bracket Modal */}
      <Modal
        isOpen={showBracketModal}
        onClose={() => {
          setShowBracketModal(false);
          setSelectedCompetition(null);
          setSelectedPerformers([]);
        }}
        title={bracketData?.competition?.name || 'Competition Bracket'}
        size="xl"
      >
        {bracketData ? (
          <BracketView
            data={bracketData}
            onStartVoting={(matchId) => startVotingMutation.mutate(matchId)}
            onCompleteMatch={(matchId) => completeMatchMutation.mutate(matchId)}
            isStartingVoting={startVotingMutation.isPending}
            isCompletingMatch={completeMatchMutation.isPending}
          />
        ) : selectedCompetition ? (
          <div className="space-y-4">
            <p className="text-sm text-gray-600">
              Select performers to include in this bracket (minimum 2, recommended power of 2 like 4, 8, 16):
            </p>
            <div className="grid grid-cols-2 gap-2 max-h-64 overflow-y-auto">
              {performers.map((p) => (
                <label
                  key={p.id}
                  className={`flex items-center gap-2 p-2 rounded border cursor-pointer transition-colors ${
                    selectedPerformers.includes(p.id)
                      ? 'bg-blue-50 border-blue-300'
                      : 'bg-white border-gray-200 hover:bg-gray-50'
                  }`}
                >
                  <input
                    type="checkbox"
                    checked={selectedPerformers.includes(p.id)}
                    onChange={() => togglePerformerSelection(p.id)}
                    className="rounded text-blue-600"
                  />
                  <span className="text-sm">{p.name}</span>
                </label>
              ))}
            </div>
            <div className="flex justify-between items-center pt-4 border-t">
              <span className="text-sm text-gray-500">{selectedPerformers.length} performers selected</span>
              <button
                className="btn btn-primary"
                disabled={selectedPerformers.length < 2 || generateBracketMutation.isPending}
                onClick={() =>
                  generateBracketMutation.mutate({
                    id: selectedCompetition,
                    performerIds: selectedPerformers,
                  })
                }
              >
                {generateBracketMutation.isPending ? 'Generating...' : 'Generate Bracket'}
              </button>
            </div>
          </div>
        ) : (
          <div className="animate-pulse h-64 bg-gray-200 rounded" />
        )}
      </Modal>
    </div>
  );
}

// Bracket visualization component
function BracketView({
  data,
  onStartVoting,
  onCompleteMatch,
  isStartingVoting,
  isCompletingMatch,
}: {
  data: BracketData;
  onStartVoting: (matchId: number) => void;
  onCompleteMatch: (matchId: number) => void;
  isStartingVoting: boolean;
  isCompletingMatch: boolean;
}) {
  const rounds = Object.keys(data.rounds)
    .map(Number)
    .sort((a, b) => a - b);

  const getRoundName = (round: number, total: number) => {
    if (round === total) return 'Final';
    if (round === total - 1) return 'Semi-Finals';
    if (round === total - 2) return 'Quarter-Finals';
    return `Round ${round}`;
  };

  return (
    <div className="overflow-x-auto">
      <div className="flex gap-8 min-w-max p-4">
        {rounds.map((round) => (
          <div key={round} className="flex flex-col gap-4">
            <h4 className="text-sm font-semibold text-gray-700 text-center">
              {getRoundName(round, rounds.length)}
            </h4>
            <div className="flex flex-col gap-4 justify-around h-full">
              {data.rounds[round].map((match) => (
                <div
                  key={match.id}
                  className={`border rounded-lg p-3 w-48 ${
                    match.status === 'voting'
                      ? 'border-green-400 bg-green-50'
                      : match.status === 'completed'
                      ? 'border-gray-300 bg-gray-50'
                      : 'border-gray-200'
                  }`}
                >
                  {/* Performer 1 */}
                  <div
                    className={`flex justify-between items-center p-2 rounded ${
                      match.winner_id === match.performer_1.id
                        ? 'bg-green-100 font-semibold'
                        : match.status === 'completed' && match.winner_id
                        ? 'text-gray-400'
                        : ''
                    }`}
                  >
                    <span className="text-sm truncate flex-1">
                      {match.performer_1.seed && <span className="text-gray-400 mr-1">{match.performer_1.seed}.</span>}
                      {match.performer_1.name}
                    </span>
                    {match.status !== 'pending' && (
                      <span className="text-sm font-medium ml-2">{match.performer_1.votes}</span>
                    )}
                  </div>
                  <div className="text-center text-xs text-gray-400 my-1">vs</div>
                  {/* Performer 2 */}
                  <div
                    className={`flex justify-between items-center p-2 rounded ${
                      match.winner_id === match.performer_2.id
                        ? 'bg-green-100 font-semibold'
                        : match.status === 'completed' && match.winner_id
                        ? 'text-gray-400'
                        : ''
                    }`}
                  >
                    <span className="text-sm truncate flex-1">
                      {match.performer_2.seed && <span className="text-gray-400 mr-1">{match.performer_2.seed}.</span>}
                      {match.performer_2.name}
                    </span>
                    {match.status !== 'pending' && (
                      <span className="text-sm font-medium ml-2">{match.performer_2.votes}</span>
                    )}
                  </div>

                  {/* Match controls */}
                  <div className="mt-2 pt-2 border-t">
                    {match.status === 'pending' && match.performer_1.id && match.performer_2.id && (
                      <button
                        className="btn btn-sm btn-primary w-full"
                        onClick={() => onStartVoting(match.id)}
                        disabled={isStartingVoting}
                      >
                        <Play className="w-3 h-3" />
                        Start Voting
                      </button>
                    )}
                    {match.status === 'voting' && (
                      <button
                        className="btn btn-sm btn-secondary w-full"
                        onClick={() => onCompleteMatch(match.id)}
                        disabled={isCompletingMatch}
                      >
                        <CheckCircle className="w-3 h-3" />
                        End Voting
                      </button>
                    )}
                    {match.status === 'completed' && (
                      <div className="text-center text-xs text-gray-500">Completed</div>
                    )}
                    {match.status === 'bye' && (
                      <div className="text-center text-xs text-gray-400 italic">Bye</div>
                    )}
                  </div>
                </div>
              ))}
            </div>
          </div>
        ))}

        {/* Winner display */}
        {data.competition.winner_performer_id && (
          <div className="flex flex-col justify-center">
            <div className="border-2 border-yellow-400 bg-yellow-50 rounded-lg p-4 w-48 text-center">
              <Trophy className="w-8 h-8 mx-auto text-yellow-500 mb-2" />
              <div className="font-semibold">Champion</div>
              <div className="text-sm text-gray-600">
                {/* Winner name would come from the final match */}
                Winner ID: {data.competition.winner_performer_id}
              </div>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
