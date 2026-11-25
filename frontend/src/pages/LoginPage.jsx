import React, { useState, useEffect } from "react";
import api from "../api/axios";
import { toast } from "sonner";
import { Mail, Lock, LogIn, Hexagon, Eye, EyeOff, Info } from "lucide-react";

const inputCls =
  "block w-full h-[54px] appearance-none rounded-2xl border border-slate-200 bg-slate-50 pl-12 pr-12 text-[15px] font-medium text-slate-900 shadow-sm outline-none transition-all placeholder:text-slate-400 hover:border-slate-300 focus:border-cyan-500 focus:bg-white focus:ring-4 focus:ring-cyan-500/15 dark:border-slate-700 dark:bg-slate-900/60 dark:text-white dark:placeholder:text-slate-500 dark:focus:bg-slate-900";

export default function LoginPage() {
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [showPassword, setShowPassword] = useState(false);
  const [loading, setLoading] = useState(false);
  const [mounted, setMounted] = useState(false);

  useEffect(() => {
    setMounted(true);
  }, []);

  const handleLogin = async (e) => {
    e.preventDefault();
    setLoading(true);

    try {
      const response = await api.post("/login", {
        email,
        password,
      });

      localStorage.setItem("token", response.data.token);
      localStorage.setItem("user", JSON.stringify(response.data.user));

      toast.success("Đăng nhập thành công!");
      setTimeout(() => {
        window.location.href = "/";
      }, 500);
    } catch (err) {
      toast.error(
        err.response?.data?.message || "Có lỗi xảy ra khi đăng nhập!",
      );
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="relative min-h-screen flex items-center justify-center overflow-hidden bg-slate-50 dark:bg-slate-900/50 dark:bg-slate-900/50 dark:bg-slate-950 transition-colors duration-500">
      {/* Animated Aurora Background */}
      <div className="absolute inset-0 z-0 overflow-hidden pointer-events-none">
        <div
          className={`absolute top-[-10%] left-[-10%] w-[500px] h-[500px] rounded-full mix-blend-multiply dark:mix-blend-screen filter blur-[100px] opacity-70 animate-[pulse_8s_ease-in-out_infinite] ${mounted ? "bg-orange-300 dark:bg-orange-600/40 translate-x-10 translate-y-10" : "bg-transparent"}`}
        ></div>
        <div
          className={`absolute bottom-[-10%] right-[-10%] w-[600px] h-[600px] rounded-full mix-blend-multiply dark:mix-blend-screen filter blur-[120px] opacity-60 animate-[pulse_10s_ease-in-out_infinite_reverse] ${mounted ? "bg-blue-300 dark:bg-blue-600/30 -translate-x-10 -translate-y-10" : "bg-transparent"}`}
        ></div>
        <div
          className={`absolute top-[20%] left-[40%] w-[400px] h-[400px] rounded-full mix-blend-multiply dark:mix-blend-screen filter blur-[80px] opacity-50 animate-[pulse_12s_ease-in-out_infinite] ${mounted ? "bg-amber-200 dark:bg-amber-600/30 translate-y-20" : "bg-transparent"}`}
        ></div>
        <div
          className={`absolute bottom-[20%] left-[10%] w-[350px] h-[350px] rounded-full mix-blend-multiply dark:mix-blend-screen filter blur-[90px] opacity-60 animate-[pulse_9s_ease-in-out_infinite] ${mounted ? "bg-cyan-200 dark:bg-cyan-600/30 -translate-y-10" : "bg-transparent"}`}
        ></div>
      </div>

      {/* --- Login Card --- */}
      <div
        className={`relative z-10 w-full max-w-[420px] p-8 sm:p-10 mx-4 transition-all duration-1000 transform ${mounted ? "translate-y-0 opacity-100" : "translate-y-12 opacity-0"}`}
      >
        {/* Glassmorphism Container */}
        <div className="absolute inset-0 bg-white dark:bg-slate-800/70 dark:bg-slate-900/70 backdrop-blur-2xl rounded-[2rem] border border-white/50 dark:border-slate-700/50 shadow-[0_8px_32px_0_rgba(0,0,0,0.1)] dark:shadow-[0_8px_32px_0_rgba(0,0,0,0.4)]"></div>

        <form onSubmit={handleLogin} className="relative z-10">
          {/* Header */}
          <div className="text-center mb-10 flex flex-col items-center">
            <img
              src="/logo.png"
              alt="Sunny Education"
              className="h-28 mb-4 object-contain drop-shadow-lg dark:shadow-none dark:shadow-none"
              onError={(e) => {
                e.target.onerror = null;
                e.target.style.display = "none";
                e.target.nextSibling.style.display = "block";
              }}
            />
            <h2 className="text-3xl font-black bg-clip-text text-transparent bg-gradient-to-r from-orange-500 to-amber-500 tracking-tight hidden">
              SUNNY EDUCATION
            </h2>
            <p className="text-slate-500 dark:text-slate-400 dark:text-slate-400 dark:text-slate-400 text-sm mt-2 font-medium">
              Chào mừng trở lại! Vui lòng đăng nhập.
            </p>
          </div>

          {/* Inputs */}
          <div className="space-y-5">
            {/* Email */}
            <div className="group">
              <label className="block text-xs font-bold text-slate-600 dark:text-slate-400 dark:text-slate-400 dark:text-slate-300 uppercase tracking-wider mb-2 ml-1">
                Email
              </label>
              <div className="relative">
                <div className="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none z-10 text-slate-400 group-focus-within:text-cyan-500 transition-colors">
                  <Mail size={18} />
                </div>
                <input
                  type="email"
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  required
                  placeholder="example@gmail.com"
                  className={inputCls}
                />
              </div>
            </div>

            {/* Password */}
            <div className="group">
              <label className="block text-xs font-bold text-slate-600 dark:text-slate-400 dark:text-slate-400 dark:text-slate-300 uppercase tracking-wider mb-2 ml-1">
                Mật khẩu
              </label>
              <div className="relative">
                <div className="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none z-10 text-slate-400 group-focus-within:text-cyan-500 transition-colors">
                  <Lock size={18} />
                </div>
                <input
                  type={showPassword ? "text" : "password"}
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  required
                  placeholder="••••••"
                  className={inputCls}
                />
                <button
                  type="button"
                  onClick={() => setShowPassword((v) => !v)}
                  aria-label={showPassword ? "Ẩn mật khẩu" : "Hiện mật khẩu"}
                  className="absolute inset-y-0 right-0 pr-4 flex items-center text-slate-400 hover:text-cyan-500 transition-colors"
                >
                  {showPassword ? <EyeOff size={18} /> : <Eye size={18} />}
                </button>
              </div>
            </div>
          </div>

          {/* Demo account note */}
          <div className="mt-6 rounded-2xl border border-cyan-100 bg-cyan-50/70 p-4 text-sm text-slate-600 dark:border-cyan-900/40 dark:bg-cyan-900/20 dark:text-slate-300">
            <p className="mb-2 flex items-center gap-2 font-bold text-cyan-700 dark:text-cyan-300">
              <Info size={16} /> Tài khoản dùng thử
            </p>
            <div className="flex items-center justify-between gap-2">
              <div className="space-y-0.5">
                <p>
                  Email: <span className="font-semibold">admin@gmail.com</span>
                </p>
                <p>
                  Mật khẩu: <span className="font-semibold">123456</span>
                </p>
              </div>
              <button
                type="button"
                onClick={() => {
                  setEmail("admin@gmail.com");
                  setPassword("123456");
                }}
                className="shrink-0 rounded-xl bg-white px-3 py-2 text-xs font-bold text-cyan-600 shadow-sm ring-1 ring-cyan-200 transition hover:bg-cyan-600 hover:text-white"
              >
                Điền nhanh
              </button>
            </div>
          </div>

          {/* Submit Button */}
          <button
            type="submit"
            disabled={loading}
            className="group appearance-none relative w-full mt-6 bg-cyan-600 bg-gradient-to-r from-cyan-500 to-blue-600 text-white font-bold py-4 rounded-2xl shadow-[0_8px_20px_rgba(6,182,212,0.3)] hover:shadow-[0_8px_25px_rgba(6,182,212,0.4)] transition-all active:scale-[0.98] disabled:opacity-70 disabled:cursor-not-allowed overflow-hidden flex items-center justify-center gap-2"
          >
            {/* Shimmer Effect */}
            <div className="absolute inset-0 -translate-x-full bg-gradient-to-r from-transparent via-white/20 to-transparent group-hover:animate-[shimmer_1.5s_infinite]"></div>

            {loading ? (
              <div className="w-5 h-5 border-2 border-white/30 border-t-white rounded-full animate-spin"></div>
            ) : (
              <>
                <LogIn
                  size={20}
                  className="transition-transform group-hover:-translate-x-1"
                />
                <span>Đăng Nhập</span>
              </>
            )}
          </button>
        </form>
      </div>
    </div>
  );
}
