import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { votingApi, showsApi } from '@/api/endpoints';
import { useToast } from '@/components/common/Toast';
import { Play, Pause, Trophy } from 'lucide-react';
import type { VoteResult } from '@/types';

export function VotingAdmin() {
  const [selectedShow, setSelectedShow] = useState<string>('');
  const queryClient = useQueryClient();
  const { addToast } = useToast();

  const { data: shows = [] } = useQuery({
    queryKey: ['shows'],
    queryFn: () => showsApi.getAll(),
  });

  const { data: config } = useQuery({
    queryKey: ['voting-config', selectedShow],
    queryFn: () => votingApi.getConfig(selectedShow),
    enabled: !!selectedShow,
  });

  const { data: results = [], isLoading: resultsLoading } = useQuery({
    queryKey: ['voting-results', selectedShow],
    queryFn: () => votingApi.getResults(selectedShow),
    enabled: !!selectedShow,
  });

  const calculateFinalsMutation = useMutation({
    mutationFn: () => votingApi.calculateFinals(selectedShow),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['voting-results', selectedShow] });
      addToast('success', 'Finals calculated successfully');
    },
    onError: (error: Error) => {
      addToast('error', error.message);
    },
  });

  const groupedResults = results.reduce(
    (acc, result) => {
      if (!acc[result.group_name]) {
        acc[result.group_name] = [];
      }
      acc[result.group_name].push(result);
      return acc;
    },
    {} as Record<string, VoteResult[]>
  );

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-900">Voting Admin</h1>
      </div>

      {/* Show Selector */}
      <div className="card p-6">
        <label className="block text-sm font-medium text-gray-700 mb-2" htmlFor="votingadmin-select-show">Select Show</label>
        <select id="votingadmin-select-show"
          className="input max-w-md"
          value={selectedShow}
          onChange={(e) => setSelectedShow(e.target.value)}
        >
          <option value="">Choose a show...</option>
          {shows.map((show) => (
            <option key={show.id} value={show.slug}>
              {show.title}
            </option>
          ))}
        </select>

        {!selectedShow && shows.length === 0 && (
          <p className="mt-3 text-sm text-gray-500">
            No shows available. Create a show first to manage voting.
          </p>
        )}

        {!selectedShow && shows.length > 0 && (
          <p className="mt-3 text-sm text-gray-500">
            Select a show above to view and manage voting controls and results.
          </p>
        )}
      </div>

      {selectedShow && (
        <>
          {/* Voting Controls */}
          <div className="card p-6">
            <h2 className="text-lg font-semibold mb-4">Voting Controls</h2>
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div className="p-4 bg-gray-50 rounded-lg">
                <div className="text-sm text-gray-500 mb-1">Active Group</div>
                <div className="font-semibold">{config?.active_group || 'None'}</div>
              </div>
              <div className="p-4 bg-gray-50 rounded-lg">
                <div className="text-sm text-gray-500 mb-1">Timer Duration</div>
                <div className="font-semibold">{config?.timer_duration || 0} seconds</div>
              </div>
              <div className="p-4 bg-gray-50 rounded-lg">
                <div className="text-sm text-gray-500 mb-1">Groups</div>
                <div className="font-semibold">{config?.num_groups || 0} groups</div>
              </div>
            </div>

            <div className="flex gap-2 mt-4">
              <button className="btn btn-primary">
                <Play className="w-4 h-4" />
                Start Voting
              </button>
              <button className="btn btn-secondary">
                <Pause className="w-4 h-4" />
                Pause
              </button>
              <button
                className="btn btn-secondary"
                onClick={() => calculateFinalsMutation.mutate()}
                disabled={calculateFinalsMutation.isPending}
              >
                <Trophy className="w-4 h-4" />
                Calculate Finals
              </button>
            </div>
          </div>

          {/* Results by Group */}
          <div className="space-y-6">
            <h2 className="text-lg font-semibold">Results by Group</h2>

            {resultsLoading ? (
              <div className="animate-pulse h-64 bg-gray-200 rounded-xl" />
            ) : Object.keys(groupedResults).length === 0 ? (
              <div className="card p-6 text-center text-gray-500">
                No votes recorded yet for this show.
              </div>
            ) : (
              Object.entries(groupedResults).map(([groupName, groupResults]) => (
                <div key={groupName} className="card overflow-hidden">
                  <div className="px-4 py-3 bg-gray-50 border-b">
                    <h3 className="font-medium">{groupName}</h3>
                  </div>
                  <table className="table">
                    <thead>
                      <tr>
                        <th>Rank</th>
                        <th>Performer</th>
                        <th className="text-center">1st</th>
                        <th className="text-center">2nd</th>
                        <th className="text-center">3rd</th>
                        <th className="text-center">Total</th>
                        <th className="text-right">Score</th>
                      </tr>
                    </thead>
                    <tbody>
                      {groupResults
                        .sort((a, b) => b.weighted_score - a.weighted_score)
                        .map((result, index) => (
                          <tr key={result.performer_id}>
                            <td>
                              {index === 0 ? (
                                <span className="text-yellow-500">🥇</span>
                              ) : index === 1 ? (
                                <span className="text-gray-400">🥈</span>
                              ) : index === 2 ? (
                                <span className="text-amber-600">🥉</span>
                              ) : (
                                index + 1
                              )}
                            </td>
                            <td>
                              <div className="flex items-center gap-2">
                                {result.photo_url ? (
                                  <img
                                    src={result.photo_url}
                                    alt={result.performer_name}
                                    className="w-8 h-8 rounded-full object-cover"
                                  />
                                ) : (
                                  <div className="w-8 h-8 rounded-full bg-gray-200" />
                                )}
                                {result.performer_name}
                              </div>
                            </td>
                            <td className="text-center">{result.first_place}</td>
                            <td className="text-center">{result.second_place}</td>
                            <td className="text-center">{result.third_place}</td>
                            <td className="text-center">{result.total_votes}</td>
                            <td className="text-right font-semibold">
                              {result.weighted_score.toFixed(1)}
                            </td>
                          </tr>
                        ))}
                    </tbody>
                  </table>
                </div>
              ))
            )}
          </div>
        </>
      )}
    </div>
  );
}
