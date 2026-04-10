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

interface WhatsAppPayload {
  connection: {
    business_id: string | null;
    waba_id: string | null;
    phone_number_id: string | null;
    display_phone_number: string | null;
    verified_name: string | null;
    quality_rating: string | null;
    has_access_token: boolean;
    masked_access_token: string | null;
    token_expires_at: string | null;
    updated_at: string | null;
  } | null;
}

export default function IntegrationSettings({ authState, onUpdated }: IntegrationSettingsProps) {
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [testingConnection, setTestingConnection] = useState(false);
  const [inviteLoading, setInviteLoading] = useState(false);
  const [whatsAppLoading, setWhatsAppLoading] = useState(true);
  const [whatsAppSaving, setWhatsAppSaving] = useState(false);
  const [whatsAppTesting, setWhatsAppTesting] = useState(false);
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
  const [whatsAppInfo, setWhatsAppInfo] = useState<WhatsAppPayload['connection']>(null);
  const [whatsAppAuthCode, setWhatsAppAuthCode] = useState('');
  const [whatsAppBusinessId, setWhatsAppBusinessId] = useState('');
  const [whatsAppWabaId, setWhatsAppWabaId] = useState('');
  const [whatsAppPhoneNumberId, setWhatsAppPhoneNumberId] = useState('');
  const [whatsAppError, setWhatsAppError] = useState('');
  const [whatsAppSuccess, setWhatsAppSuccess] = useState('');

  useEffect(() => {
    void Promise.all([loadConnection(), loadWhatsAppConnection()]);
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

  const loadWhatsAppConnection = async () => {
    setWhatsAppLoading(true);

    try {
      const response = await api.get<WhatsAppPayload>('/whatsapp');
      setWhatsAppInfo(response.data.connection);
      setWhatsAppBusinessId(response.data.connection?.business_id ?? '');
      setWhatsAppWabaId(response.data.connection?.waba_id ?? '');
      setWhatsAppPhoneNumberId(response.data.connection?.phone_number_id ?? '');
    } catch (err: any) {
      setWhatsAppError(err.response?.data?.error || 'Nao foi possivel carregar o WhatsApp.');
    } finally {
      setWhatsAppLoading(false);
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
        has_whatsapp: authState.has_whatsapp,
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

  const handleWhatsAppConnect = async (event: React.FormEvent) => {
    event.preventDefault();
    setWhatsAppSaving(true);
    setWhatsAppError('');
    setWhatsAppSuccess('');

    try {
      const response = await api.post<{
        success: boolean;
        connection: WhatsAppPayload['connection'];
      }>('/whatsapp/connect', {
        authorization_code: whatsAppAuthCode,
        business_id: whatsAppBusinessId || null,
        waba_id: whatsAppWabaId || null,
        phone_number_id: whatsAppPhoneNumberId || null,
      });

      setWhatsAppInfo(response.data.connection);
      setWhatsAppAuthCode('');
      setWhatsAppSuccess('WhatsApp conectado com sucesso.');
      onUpdated({
        authenticated: true,
        user: authState.user,
        is_admin: authState.is_admin,
        has_integration: authState.has_integration,
        has_whatsapp: true,
      });
    } catch (err: any) {
      setWhatsAppError(err.response?.data?.error || 'Nao foi possivel conectar o WhatsApp.');
    } finally {
      setWhatsAppSaving(false);
    }
  };

  const handleWhatsAppTest = async () => {
    setWhatsAppTesting(true);
    setWhatsAppError('');
    setWhatsAppSuccess('');

    try {
      const response = await api.post('/whatsapp/test');
      setWhatsAppSuccess(response.data.message || 'Conexao WhatsApp validada com sucesso.');
      await loadWhatsAppConnection();
    } catch (err: any) {
      setWhatsAppError(err.response?.data?.error || 'Nao foi possivel testar o WhatsApp.');
    } finally {
      setWhatsAppTesting(false);
    }
  };

  const handleWhatsAppDisconnect = async () => {
    setWhatsAppTesting(true);
    setWhatsAppError('');
    setWhatsAppSuccess('');

    try {
      await api.delete('/whatsapp');
      setWhatsAppInfo(null);
      setWhatsAppBusinessId('');
      setWhatsAppWabaId('');
      setWhatsAppPhoneNumberId('');
      setWhatsAppSuccess('WhatsApp desconectado.');
      onUpdated({
        authenticated: true,
        user: authState.user,
        is_admin: authState.is_admin,
        has_integration: authState.has_integration,
        has_whatsapp: false,
      });
    } catch (err: any) {
      setWhatsAppError(err.response?.data?.error || 'Nao foi possivel desconectar o WhatsApp.');
    } finally {
      setWhatsAppTesting(false);
    }
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

          <motion.section
            initial={{ opacity: 0, y: 12 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 0.05 }}
            className="card-modern p-6 sm:p-8"
          >
            <div className="mb-8">
              <div className="mb-2 inline-flex items-center gap-2 rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold uppercase tracking-widest text-emerald-700">
                <PlugZap size={14} />
                WhatsApp
              </div>
              <h2 className="text-xl font-bold text-slate-900">Conectar WhatsApp (Cloud API)</h2>
              <p className="mt-2 max-w-2xl text-sm text-slate-500">
                Use os dados do Embedded Signup para vincular o numero do WhatsApp Business a sua conta do WooPack. Esta conexao e individual por usuario.
              </p>
            </div>

            {whatsAppLoading ? (
              <div className="text-sm text-slate-500">Carregando WhatsApp...</div>
            ) : (
              <div className="space-y-6">
                {whatsAppInfo && (
                  <div className="grid gap-4 rounded-2xl border border-slate-200 bg-slate-50 p-4 sm:grid-cols-2">
                    <div>
                      <div className="text-xs font-bold uppercase tracking-widest text-slate-400">Numero</div>
                      <div className="mt-1 text-sm font-semibold text-slate-700">{whatsAppInfo.display_phone_number ?? '-'}</div>
                      <div className="mt-1 text-xs text-slate-500">{whatsAppInfo.verified_name ?? ''}</div>
                    </div>
                    <div>
                      <div className="text-xs font-bold uppercase tracking-widest text-slate-400">Token</div>
                      <div className="mt-1 text-sm font-semibold text-slate-700">{whatsAppInfo.masked_access_token ?? '-'}</div>
                      <div className="mt-1 text-xs text-slate-500">{whatsAppInfo.token_expires_at ? `Expira em ${new Date(whatsAppInfo.token_expires_at).toLocaleDateString()}` : ''}</div>
                    </div>
                    <div className="sm:col-span-2 grid gap-2 text-xs text-slate-500 sm:grid-cols-3">
                      <div>Business: {whatsAppInfo.business_id ?? '-'}</div>
                      <div>WABA: {whatsAppInfo.waba_id ?? '-'}</div>
                      <div>Phone ID: {whatsAppInfo.phone_number_id ?? '-'}</div>
                    </div>
                  </div>
                )}

                <form onSubmit={handleWhatsAppConnect} className="space-y-5">
                  <div className="space-y-2">
                    <label className="text-sm font-medium text-slate-700">Authorization code (Embedded Signup)</label>
                    <textarea
                      value={whatsAppAuthCode}
                      onChange={(event) => setWhatsAppAuthCode(event.target.value)}
                      placeholder="Cole aqui o code AQB..."
                      className="input-modern min-h-[100px] resize-y"
                      required
                    />
                    <p className="text-xs text-slate-500">Esse codigo e trocado por um access token e nao fica visivel depois de salvo.</p>
                  </div>

                  <div className="grid gap-4 sm:grid-cols-3">
                    <div className="space-y-2">
                      <label className="text-sm font-medium text-slate-700">Business ID</label>
                      <input
                        value={whatsAppBusinessId}
                        onChange={(event) => setWhatsAppBusinessId(event.target.value)}
                        placeholder="2016..."
                        className="input-modern"
                      />
                    </div>
                    <div className="space-y-2">
                      <label className="text-sm font-medium text-slate-700">WABA ID</label>
                      <input value={whatsAppWabaId} onChange={(event) => setWhatsAppWabaId(event.target.value)} placeholder="2670..." className="input-modern" />
                    </div>
                    <div className="space-y-2">
                      <label className="text-sm font-medium text-slate-700">Phone number ID</label>
                      <input
                        value={whatsAppPhoneNumberId}
                        onChange={(event) => setWhatsAppPhoneNumberId(event.target.value)}
                        placeholder="1092..."
                        className="input-modern"
                      />
                    </div>
                  </div>

                  {whatsAppError && <div className="rounded-xl border border-red-100 bg-red-50 px-4 py-3 text-sm text-red-600">{whatsAppError}</div>}
                  {whatsAppSuccess && <div className="rounded-xl border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{whatsAppSuccess}</div>}

                  <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <p className="text-sm text-slate-500">Depois de conectar, voce pode testar e desconectar quando quiser.</p>
                    <div className="flex flex-col gap-3 sm:flex-row">
                      <button
                        type="button"
                        onClick={handleWhatsAppTest}
                        disabled={whatsAppTesting}
                        className="min-w-[220px] rounded-lg border border-slate-200 bg-white px-4 py-3 font-medium text-slate-600 transition-all hover:bg-slate-50 disabled:opacity-50"
                      >
                        {whatsAppTesting ? 'Testando...' : 'Testar WhatsApp'}
                      </button>
                      {whatsAppInfo ? (
                        <button
                          type="button"
                          onClick={handleWhatsAppDisconnect}
                          disabled={whatsAppTesting}
                          className="min-w-[220px] rounded-lg border border-red-200 bg-white px-4 py-3 font-medium text-red-600 transition-all hover:bg-red-50 disabled:opacity-50"
                        >
                          Desconectar
                        </button>
                      ) : (
                        <button type="submit" disabled={whatsAppSaving} className="btn-primary min-w-[220px] py-3">
                          {whatsAppSaving ? 'Conectando...' : 'Conectar WhatsApp'}
                        </button>
                      )}
                    </div>
                  </div>
                </form>
              </div>
            )}
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
