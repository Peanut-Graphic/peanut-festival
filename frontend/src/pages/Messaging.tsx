import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
  MessageSquare,
  Send,
  User,
  Mail,
  Radio,
  X,
} from 'lucide-react';
import { client } from '../api/client';
import { useToast } from '../components/common/Toast';
import type { Conversation, Message } from '../types';

const broadcastGroups = [
  { value: 'all', label: 'All Participants', description: 'Everyone in the festival' },
  { value: 'performers', label: 'All Performers', description: 'Accepted performers only' },
  { value: 'volunteers', label: 'All Volunteers', description: 'Active volunteers' },
  { value: 'vendors', label: 'All Vendors', description: 'Approved vendors' },
  { value: 'attendees', label: 'All Attendees', description: 'Ticket holders' },
];

export function Messaging() {
  const [activeTab, setActiveTab] = useState<'inbox' | 'broadcast'>('inbox');
  const [selectedConversation, setSelectedConversation] = useState<string | null>(null);
  const [isBroadcastModalOpen, setIsBroadcastModalOpen] = useState(false);

  const { addToast } = useToast();
  const queryClient = useQueryClient();

  // Fetch conversations
  const { data: conversations = [], isLoading: conversationsLoading } = useQuery({
    queryKey: ['conversations'],
    queryFn: async () => {
      const res = await client.get<{ data: Conversation[] }>('/messages/conversations');
      return res.data.data;
    },
  });

  // Fetch messages for selected conversation
  const { data: messages = [], isLoading: messagesLoading } = useQuery({
    queryKey: ['messages', selectedConversation],
    queryFn: async () => {
      if (!selectedConversation) return [];
      const res = await client.get<{ data: Message[] }>(`/messages/${selectedConversation}`);
      return res.data.data;
    },
    enabled: !!selectedConversation,
  });

  // Send broadcast mutation
  const sendBroadcastMutation = useMutation({
    mutationFn: (data: { group: string; subject: string; content: string }) =>
      client.post('/messages/broadcast', data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['conversations'] });
      setIsBroadcastModalOpen(false);
      addToast('success', 'Broadcast sent successfully');
    },
    onError: () => addToast('error', 'Failed to send broadcast'),
  });

  // Mark as read mutation
  const markAsReadMutation = useMutation({
    mutationFn: (conversationId: string) =>
      client.post(`/messages/${conversationId}/read`),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['conversations'] });
    },
  });

  const handleSelectConversation = (conversationId: string) => {
    setSelectedConversation(conversationId);
    const conversation = conversations.find((c) => c.conversation_id === conversationId);
    if (conversation && conversation.unread_count > 0) {
      markAsReadMutation.mutate(conversationId);
    }
  };

  const totalUnread = conversations.reduce((sum, c) => sum + c.unread_count, 0);

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-900">Messages</h1>
        <button
          onClick={() => setIsBroadcastModalOpen(true)}
          className="inline-flex items-center gap-2 px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700"
        >
          <Radio className="w-5 h-5" />
          Send Broadcast
        </button>
      </div>

      {/* Tabs */}
      <div className="border-b border-gray-200">
        <nav className="-mb-px flex gap-6">
          <button
            onClick={() => setActiveTab('inbox')}
            className={`py-3 px-1 border-b-2 font-medium text-sm ${
              activeTab === 'inbox'
                ? 'border-primary-500 text-primary-600'
                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
            }`}
          >
            <Mail className="w-4 h-4 inline mr-2" />
            Inbox
            {totalUnread > 0 && (
              <span className="ml-2 px-2 py-0.5 bg-primary-100 text-primary-700 text-xs rounded-full">
                {totalUnread}
              </span>
            )}
          </button>
          <button
            onClick={() => setActiveTab('broadcast')}
            className={`py-3 px-1 border-b-2 font-medium text-sm ${
              activeTab === 'broadcast'
                ? 'border-primary-500 text-primary-600'
                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
            }`}
          >
            <Radio className="w-4 h-4 inline mr-2" />
            Broadcast History
          </button>
        </nav>
      </div>

      {/* Inbox */}
      {activeTab === 'inbox' && (
        <div className="grid gap-6 lg:grid-cols-[320px_1fr]">
          {/* Conversations List */}
          <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div className="p-4 border-b bg-gray-50">
              <h3 className="font-medium text-gray-900">Conversations</h3>
            </div>
            {conversationsLoading ? (
              <div className="p-8 text-center text-gray-500">Loading...</div>
            ) : conversations.length === 0 ? (
              <div className="p-8 text-center">
                <MessageSquare className="w-12 h-12 mx-auto text-gray-300 mb-3" />
                <p className="text-gray-500">No conversations yet</p>
              </div>
            ) : (
              <div className="divide-y divide-gray-200 max-h-[500px] overflow-y-auto">
                {conversations.map((conversation) => (
                  <button
                    key={conversation.conversation_id}
                    onClick={() => handleSelectConversation(conversation.conversation_id)}
                    className={`w-full px-4 py-3 text-left hover:bg-gray-50 transition-colors ${
                      selectedConversation === conversation.conversation_id
                        ? 'bg-primary-50'
                        : ''
                    }`}
                  >
                    <div className="flex items-center gap-3">
                      <div className="w-10 h-10 rounded-full bg-gray-200 flex items-center justify-center">
                        <User className="w-5 h-5 text-gray-500" />
                      </div>
                      <div className="flex-1 min-w-0">
                        <p className="font-medium text-gray-900 truncate">
                          {conversation.conversation_id}
                        </p>
                        <p className="text-xs text-gray-500">
                          {new Date(conversation.last_message_at).toLocaleDateString()}
                        </p>
                      </div>
                      {conversation.unread_count > 0 && (
                        <span className="px-2 py-0.5 bg-primary-600 text-white text-xs rounded-full">
                          {conversation.unread_count}
                        </span>
                      )}
                    </div>
                  </button>
                ))}
              </div>
            )}
          </div>

          {/* Messages */}
          <div className="bg-white rounded-xl border border-gray-200 overflow-hidden flex flex-col">
            {!selectedConversation ? (
              <div className="flex-1 flex items-center justify-center p-8">
                <div className="text-center">
                  <MessageSquare className="w-16 h-16 mx-auto text-gray-300 mb-4" />
                  <p className="text-gray-500">Select a conversation to view messages</p>
                </div>
              </div>
            ) : (
              <>
                <div className="p-4 border-b bg-gray-50">
                  <h3 className="font-medium text-gray-900">{selectedConversation}</h3>
                </div>
                <div className="flex-1 p-4 space-y-4 max-h-[400px] overflow-y-auto">
                  {messagesLoading ? (
                    <div className="text-center text-gray-500">Loading messages...</div>
                  ) : messages.length === 0 ? (
                    <div className="text-center text-gray-500">No messages in this conversation</div>
                  ) : (
                    messages.map((message) => (
                      <div
                        key={message.id}
                        className={`flex ${
                          message.sender_type === 'admin' ? 'justify-end' : 'justify-start'
                        }`}
                      >
                        <div
                          className={`max-w-[70%] px-4 py-2 rounded-lg ${
                            message.sender_type === 'admin'
                              ? 'bg-primary-600 text-white'
                              : 'bg-gray-100 text-gray-900'
                          }`}
                        >
                          {message.subject && (
                            <p className="font-medium text-sm mb-1">{message.subject}</p>
                          )}
                          <p className="text-sm">{message.content}</p>
                          <p
                            className={`text-xs mt-1 ${
                              message.sender_type === 'admin'
                                ? 'text-primary-200'
                                : 'text-gray-500'
                            }`}
                          >
                            {new Date(message.created_at).toLocaleTimeString()}
                          </p>
                        </div>
                      </div>
                    ))
                  )}
                </div>
                <MessageComposer
                  conversationId={selectedConversation}
                  onSent={() => queryClient.invalidateQueries({ queryKey: ['messages', selectedConversation] })}
                />
              </>
            )}
          </div>
        </div>
      )}

      {/* Broadcast History */}
      {activeTab === 'broadcast' && (
        <div className="bg-white rounded-xl border border-gray-200">
          <div className="p-4 border-b bg-gray-50">
            <h3 className="font-medium text-gray-900">Broadcast History</h3>
          </div>
          <div className="p-8 text-center text-gray-500">
            <Radio className="w-12 h-12 mx-auto text-gray-300 mb-4" />
            <p>Broadcast messages will appear here</p>
            <p className="text-sm mt-1">Send a broadcast to communicate with groups</p>
          </div>
        </div>
      )}

      {/* Broadcast Modal */}
      {isBroadcastModalOpen && (
        <BroadcastModal
          onClose={() => setIsBroadcastModalOpen(false)}
          onSend={(data) => sendBroadcastMutation.mutate(data)}
          isSending={sendBroadcastMutation.isPending}
        />
      )}
    </div>
  );
}

// Message Composer Component
function MessageComposer({
  conversationId,
  onSent,
}: {
  conversationId: string;
  onSent: () => void;
}) {
  const [message, setMessage] = useState('');
  const { addToast } = useToast();

  const sendMutation = useMutation({
    mutationFn: (content: string) =>
      client.post('/messages', {
        conversation_id: conversationId,
        content,
      }),
    onSuccess: () => {
      setMessage('');
      onSent();
    },
    onError: () => addToast('error', 'Failed to send message'),
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!message.trim()) return;
    sendMutation.mutate(message);
  };

  return (
    <form onSubmit={handleSubmit} className="p-4 border-t bg-gray-50">
      <div className="flex gap-2">
        <input
          type="text"
          value={message}
          onChange={(e) => setMessage(e.target.value)}
          placeholder="Type a message..."
          className="flex-1 px-4 py-2 text-gray-900 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
        />
        <button
          type="submit"
          disabled={!message.trim() || sendMutation.isPending}
          className="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 disabled:opacity-50"
        >
          <Send className="w-5 h-5" />
        </button>
      </div>
    </form>
  );
}

// Broadcast Modal
function BroadcastModal({
  onClose,
  onSend,
  isSending,
}: {
  onClose: () => void;
  onSend: (data: { group: string; subject: string; content: string }) => void;
  isSending: boolean;
}) {
  const [formData, setFormData] = useState({
    group: 'all',
    subject: '',
    content: '',
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!formData.content.trim()) return;
    onSend(formData);
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
      <div className="bg-white rounded-xl shadow-xl max-w-lg w-full mx-4">
        <form onSubmit={handleSubmit}>
          <div className="flex items-center justify-between p-4 border-b">
            <h2 className="text-lg font-semibold">Send Broadcast</h2>
            <button type="button" onClick={onClose} className="p-2 hover:bg-gray-100 rounded-lg">
              <X className="w-5 h-5" />
            </button>
          </div>

          <div className="p-4 space-y-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Send To
              </label>
              <div className="space-y-2">
                {broadcastGroups.map((group) => (
                  <label
                    key={group.value}
                    className={`flex items-center gap-3 p-3 border rounded-lg cursor-pointer transition-colors ${
                      formData.group === group.value
                        ? 'border-primary-500 bg-primary-50'
                        : 'border-gray-200 hover:bg-gray-50'
                    }`}
                  >
                    <input
                      type="radio"
                      name="group"
                      value={group.value}
                      checked={formData.group === group.value}
                      onChange={(e) => setFormData({ ...formData, group: e.target.value })}
                      className="w-4 h-4 text-primary-600"
                    />
                    <div>
                      <p className="font-medium text-gray-900">{group.label}</p>
                      <p className="text-xs text-gray-500">{group.description}</p>
                    </div>
                  </label>
                ))}
              </div>
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1" htmlFor="messaging-subject">
                Subject
              </label>
              <input id="messaging-subject"
                type="text"
                value={formData.subject}
                onChange={(e) => setFormData({ ...formData, subject: e.target.value })}
                className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                placeholder="Message subject"
              />
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1" htmlFor="messaging-message">
                Message *
              </label>
              <textarea id="messaging-message"
                value={formData.content}
                onChange={(e) => setFormData({ ...formData, content: e.target.value })}
                rows={5}
                className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 resize-none"
                placeholder="Write your message here..."
                required
              />
            </div>
          </div>

          <div className="flex justify-end gap-3 p-4 border-t bg-gray-50">
            <button
              type="button"
              onClick={onClose}
              className="px-4 py-2 text-gray-700 hover:bg-gray-100 rounded-lg"
            >
              Cancel
            </button>
            <button
              type="submit"
              disabled={isSending || !formData.content.trim()}
              className="inline-flex items-center gap-2 px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 disabled:opacity-50"
            >
              <Radio className="w-4 h-4" />
              {isSending ? 'Sending...' : 'Send Broadcast'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
