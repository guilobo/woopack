import { useEffect, useState } from 'react';
import { CheckCircle2, Copy, KeyRound, Link as LinkIcon, PlugZap, UserPlus } from 'lucide-react';
import { motion } from 'motion/react';
import api from '../api';
import type { AuthState } from '../App';

interface IntegrationSettingsProps {
  authState: AuthState;
  onUpdated: (payload: Partial<AuthState>) => void;
}

interface ConnectionPayload {
  connection: {
    store_url: string;
    has_consumer_key: boolean;
    has_consumer_secret: boolean;
    masked_consumer_key: string | null;
    masked_consumer_secret: string | null;
    updated_at: string | null;
  } | null;
}

export default function IntegrationSettings({ authState, onUpdated }: IntegrationSettingsProps) {
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [testingConnection, setTestingConnection] = useState(false);
  const [inviteLoading, setInviteLoading] = useState(false);
  const [storeUrl, setStoreUrl] = useState('');
  const [consumerKey, setConsumerKey] = useState('');
  const [consumerSecret, setConsumerSecret] = useState('');
  const [inviteEmail, setInviteEmail] = useState('');
  const [inviteUrl, setInviteUrl] = useState('');
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [inviteError, setInviteError] = useState('');
  const [inviteSuccess, setInviteSuccess] = useState('');
  const [connectionInfo, setConnectionInfo] = useState<ConnectionPayload['connection']>(null);
  const [connectionTestMessage, setConnectionTestMessage] = useState('');
  const [connectionTestError, setConnectionTestError] = useState('');

  useEffect(() => {
    void loadConnection();
  }, []);

  const loadConnection = async () => {
    setLoading(true);

    try {
      const response = await api.get<ConnectionPayload>('/integration');
      setConnectionInfo(response.data.connection);
      setStoreUrl(response.data.connection?.store_url ?? '');
    } catch (err: any) {
      setError(err.response?.data?.error || 'Nao foi possivel carregar a integracao.');
    } finally {
      setLoading(false);
    }
  };

  const handleSave = async (event: React.FormEvent) => {
    event.preventDefault();
    setSaving(true);
    setError('');
    setSuccess('');
    setConnectionTestError('');
    setConnectionTestMessage('');

    try {
      const response = await api.put<ConnectionPayload & { success: boolean }>('/integration', {
        store_url: storeUrl,
        consumer_key: consumerKey,
        consumer_secret: consumerSecret,
      });

      setConnectionInfo(response.data.connection);
      setConsumerKey('');
      setConsumerSecret('');
      setSuccess('Integracao WooCommerce salva com sucesso.');
      onUpdated({
        authenticated: true,
        user: authState.user,
        is_admin: authState.is_admin,
        has_integration: true,
      });
    } catch (err: any) {
      setError(err.response?.data?.message || err.response?.data?.error || 'Nao foi possivel salvar a integracao.');
    } finally {
      setSaving(false);
    }
  };

  const handleTestConnection = async () => {
    setTestingConnection(true);
    setConnectionTestError('');
    setConnectionTestMessage('');
    setError('');
    setSuccess('');

    try {
      const response = await api.post('/integration/test', {
        store_url: storeUrl,
        consumer_key: consumerKey,
        consumer_secret: consumerSecret,
      });

      setConnectionTestMessage(response.data.message || 'Conexao WooCommerce validada com sucesso.');
    } catch (err: any) {
      setConnectionTestError(err.response?.data?.message || err.response?.data?.error || 'Nao foi possivel testar a conexao.');
    } finally {
      setTestingConnection(false);
    }
  };

  const handleInvite = async (event: React.FormEvent) => {
    event.preventDefault();
    setInviteLoading(true);
    setInviteError('');
    setInviteSuccess('');
    setInviteUrl('');

    try {
      const response = await api.post('/invitations', { email: inviteEmail });
      setInviteUrl(response.data.invitation.accept_url);
      setInviteSuccess(`Convite criado para ${response.data.invitation.email}.`);
      setInviteEmail('');
    } catch (err: any) {
      setInviteError(err.response?.data?.message || err.response?.data?.error || 'Nao foi possivel gerar o convite.');
    } finally {
      setInviteLoading(false);
    }
  };

  const copyInviteLink = async () => {
    if (!inviteUrl) {
      return;
    }

    await navigator.clipboard.writeText(inviteUrl);
    setInviteSuccess('Link copiado para a area de transferencia.');
  };

  return (
    <div className="space-y-8">
      <header className="space-y-2">
        <h1 className="text-3xl font-bold tracking-tight text-slate-900">Integracao da Conta</h1>
        <p className="max-w-2xl text-slate-500">
          Cada usuario trabalha com a propria loja WooCommerce. Configure a conexao abaixo para liberar o dashboard, os pedidos e o modo embalagem.
        </p>
      </header>

      {loading ? (
        <div className="flex h-64 items-center justify-center">
          <div className="h-10 w-10 animate-spin rounded-full border-4 border-primary border-t-transparent" />
        </div>
      ) : (
        <>
          <motion.section
            initial={{ opacity: 0, y: 12 }}
            animate={{ opacity: 1, y: 0 }}
            className="card-modern p-6 sm:p-8"
          >
            <div className="mb-8 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
              <div>
                <div className="mb-2 inline-flex items-center gap-2 rounded-full bg-primary/10 px-3 py-1 text-xs font-bold uppercase tracking-widest text-primary">
                  <PlugZap size={14} />
                  WooCommerce
                </div>
                <h2 className="text-xl font-bold text-slate-900">Conexao da loja</h2>
                <p className="mt-2 text-sm text-slate-500">
                  As credenciais ficam salvas por usuario. URL, chave e segredo podem ser atualizados a qualquer momento.
                </p>
              </div>

              <div className="rounded-2xl bg-slate-50 px-4 py-3 text-sm text-slate-500">
                <div className="font-semibold text-slate-900">
                  {connectionInfo ? 'Conexao configurada' : 'Conexao pendente'}
                </div>
                <div className="mt-1">
                  {connectionInfo?.updated_at
                    ? `Atualizada em ${new Date(connectionInfo.updated_at).toLocaleString('pt-BR')}`
                    : 'Preencha os dados para liberar o painel.'}
                </div>
              </div>
            </div>

            <form onSubmit={handleSave} className="space-y-6">
              <div className="space-y-2">
                <label className="text-sm font-medium text-slate-700">URL da loja</label>
                <div className="relative">
                  <LinkIcon size={18} className="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400" />
                  <input
                    type="text"
                    value={storeUrl}
                    onChange={(event) => setStoreUrl(event.target.value)}
                    placeholder="https://minhaloja.com.br"
                    className="input-modern input-modern-icon"
                    required
                  />
                </div>
              </div>

              <div className="grid gap-6 lg:grid-cols-2">
                <div className="space-y-2">
                  <label className="text-sm font-medium text-slate-700">Consumer key</label>
                  <div className="relative">
                    <KeyRound size={18} className="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400" />
                    <input
                      type="text"
                      value={consumerKey}
                      onChange={(event) => setConsumerKey(event.target.value)}
                      placeholder={connectionInfo?.masked_consumer_key ?? 'ck_...'}
                      className="input-modern input-modern-icon"
                      required={!connectionInfo}
                      autoComplete="off"
                    />
                  </div>
                  {connectionInfo?.masked_consumer_key && (
                    <p className="text-xs text-slate-400">
                      Chave atual: <span className="font-mono text-slate-500">{connectionInfo.masked_consumer_key}</span>
                    </p>
                  )}
                </div>

                <div className="space-y-2">
                  <label className="text-sm font-medium text-slate-700">Consumer secret</label>
                  <div className="relative">
                    <KeyRound size={18} className="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400" />
                    <input
                      type="text"
                      value={consumerSecret}
                      onChange={(event) => setConsumerSecret(event.target.value)}
                      placeholder={connectionInfo?.masked_consumer_secret ?? 'cs_...'}
                      className="input-modern input-modern-icon"
                      required={!connectionInfo}
                      autoComplete="off"
                    />
                  </div>
                  {connectionInfo?.masked_consumer_secret && (
                    <p className="text-xs text-slate-400">
                      Segredo atual: <span className="font-mono text-slate-500">{connectionInfo.masked_consumer_secret}</span>
                    </p>
                  )}
                </div>
              </div>

              {error && <div className="rounded-xl border border-red-100 bg-red-50 px-4 py-3 text-sm text-red-600">{error}</div>}
              {success && <div className="rounded-xl border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{success}</div>}
              {connectionTestError && <div className="rounded-xl border border-red-100 bg-red-50 px-4 py-3 text-sm text-red-600">{connectionTestError}</div>}
              {connectionTestMessage && (
                <div className="rounded-xl border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                  <div className="flex items-center gap-2">
                    <CheckCircle2 size={16} />
                    {connectionTestMessage}
                  </div>
                </div>
              )}

              <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p className="text-sm text-slate-500">
                  {connectionInfo
                    ? 'Voce pode atualizar so a URL ou reenviar as credenciais quando precisar.'
                    : 'Depois de salvar, o dashboard e os pedidos serao liberados para esta conta.'}
                </p>
                <div className="flex flex-col gap-3 sm:flex-row">
                  <button
                    type="button"
                    onClick={handleTestConnection}
                    disabled={testingConnection}
                    className="min-w-[220px] rounded-lg border border-slate-200 bg-white px-4 py-3 font-medium text-slate-600 transition-all hover:bg-slate-50 disabled:opacity-50"
                  >
                    {testingConnection ? 'Testando...' : 'Testar conexao'}
                  </button>
                  <button type="submit" disabled={saving} className="btn-primary min-w-[220px] py-3">
                    {saving ? 'Salvando...' : connectionInfo ? 'Atualizar integracao' : 'Salvar integracao'}
                  </button>
                </div>
              </div>
            </form>
          </motion.section>

          {authState.is_admin && (
            <motion.section
              initial={{ opacity: 0, y: 12 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ delay: 0.08 }}
              className="card-modern p-6 sm:p-8"
            >
              <div className="mb-8">
                <div className="mb-2 inline-flex items-center gap-2 rounded-full bg-indigo-50 px-3 py-1 text-xs font-bold uppercase tracking-widest text-indigo-600">
                  <UserPlus size={14} />
                  Convites
                </div>
                <h2 className="text-xl font-bold text-slate-900">Convidar novo usuario</h2>
                <p className="mt-2 max-w-2xl text-sm text-slate-500">
                  O cadastro do WooPack e controlado por convite. Gere um link unico e envie para quem precisa criar a propria conta.
                </p>
              </div>

              <form onSubmit={handleInvite} className="space-y-6">
                <div className="space-y-2">
                  <label className="text-sm font-medium text-slate-700">E-mail do convidado</label>
                  <input
                    type="email"
                    value={inviteEmail}
                    onChange={(event) => setInviteEmail(event.target.value)}
                    placeholder="novo.usuario@empresa.com"
                    className="input-modern"
                    required
                  />
                </div>

                {inviteError && <div className="rounded-xl border border-red-100 bg-red-50 px-4 py-3 text-sm text-red-600">{inviteError}</div>}
                {inviteSuccess && <div className="rounded-xl border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{inviteSuccess}</div>}

                {inviteUrl && (
                  <div className="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <div className="mb-2 text-xs font-bold uppercase tracking-widest text-slate-400">Link gerado</div>
                    <div className="break-all text-sm font-medium text-slate-700">{inviteUrl}</div>
                    <button type="button" onClick={copyInviteLink} className="mt-4 inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-100">
                      <Copy size={16} />
                      Copiar link
                    </button>
                  </div>
                )}

                <button type="submit" disabled={inviteLoading} className="btn-primary py-3">
                  {inviteLoading ? 'Gerando convite...' : 'Gerar convite'}
                </button>
              </form>
            </motion.section>
          )}
        </>
      )}
    </div>
  );
}
